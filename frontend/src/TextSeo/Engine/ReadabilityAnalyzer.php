<?php

namespace SeoEngine;

class ReadabilityAnalyzer {
    private TextCleaner $cleaner;

    public function __construct(TextCleaner $cleaner) {
        $this->cleaner = $cleaner;
    }

    public function analyze(): array {
        $words = $this->cleaner->getWords();
        $sentences = $this->cleaner->getSentences();
        
        $wordCount = count($words);
        $sentenceCount = count($sentences);
        $syllableCount = $this->cleaner->getTotalSyllables();

        $wPerS = $sentenceCount > 0 ? $wordCount / $sentenceCount : 0;
        $lPerW = $wordCount > 0 ? $syllableCount / $wordCount : 0;

        // FRE = 206.835 - 1.015 * (W / S) - 84.6 * (L / W)
        $fre = 206.835 - (1.015 * $wPerS) - (84.6 * $lPerW);

        // FKGL = 0.39 * (W / S) + 11.8 * (L / W) - 15.59
        $fkgl = (0.39 * $wPerS) + (11.8 * $lPerW) - 15.59;

        // Ateşman = 198.825 - (40.175 * H_w) - (2.610 * C_w)
        $atesman = 198.825 - (40.175 * $lPerW) - (2.610 * $wPerS);
        $atesmanFeedback = $this->getAtesmanFeedback($atesman);

        // Gunning Fog
        $complexWordsCount = 0;
        foreach ($words as $word) {
            if ($this->cleaner->countSyllables($word) >= 3) {
                $complexWordsCount++;
            }
        }
        $complexRatio = $wordCount > 0 ? ($complexWordsCount / $wordCount) * 100 : 0;
        $gunningFog = 0.4 * ($wPerS + $complexRatio);
        $complexFeedback = $this->getComplexWordsFeedback($complexRatio);

        // Transition Words
        $transitionMatches = 0;
        $sentencesWithTransitions = 0;
        $transitionRegex = '/\b(çünkü|bu nedenle|örneğin|ancak|buna ek olarak|son olarak|öte yandan|dolayısıyla|ayrıca|bununla birlikte|bu yüzden|sonuç olarak|nitekim|oysa|halbuki|bundan dolayı|ilk olarak|özetle|kısacası|bilhassa|ne var ki|böylece|kısaca|başka bir deyişle|özellikle|üstelik|hatta|kaldı ki|aksi takdirde|bunun yanında|buna karşın|rağmen|yine de|oysaki|bunun sonucunda|buna bağlı olarak|öte taraftan|bununla beraber|zira|yani|aksine|bilakis|netice itibariyle|ayriyeten|özetlemek gerekirse)\b/ui';
        
        foreach ($sentences as $sentence) {
            $count = preg_match_all($transitionRegex, $sentence, $matches);
            if ($count > 0) {
                $transitionMatches += $count;
                $sentencesWithTransitions++;
            }
        }
        $transitionRatio = $sentenceCount > 0 ? ($sentencesWithTransitions / $sentenceCount) * 100 : 0;
        $transitionFeedback = $this->getTransitionWordsFeedback($transitionRatio);

        // Passive Voice
        // Regex refined to ensure the passive suffix (ıl, il, vb.) is followed by typical passive endings like mış/miş or dı/di or tır/tir, minimizing false positives like "altın(dı)" or "bütün(dü)".
        // A more targeted passive matching: \b\p{L}+(?:ıl|il|ul|ül|ın|in|un|ün)(?:dı|di|du|dü|tı|ti|tu|tü|mış|miş|muş|müş)(?:dır|dir|dur|dür|tır|tir|tur|tür)?\b
        // Let's refine it. Many Turkish words end with 'ın'/'in' or 'il'/'ıl'.
        // Let's rely on common passive verb patterns, like "yap-ıl-dı", "gör-ül-dü", "söylen-di", "sun-ul-muş-tur".
        // Instead of completely solving Turkish NLP which is hard with regex, we make it stricter to avoid false positives.
        $passiveRegexDoc = '/\b\p{L}+(?:ıl|il|ul|ül|ın|in|un|ün|n)(?:dı|di|du|dü|tı|ti|tu|tü|mış|miş|muş|müş)(?:dır|dir|dur|dür|tır|tir|tur|tür)?\b/ui';
        $passiveSentencesCount = 0;
        $passiveSentenceIndexes = [];
        
        // Exclude common non-passive words ending in similar patterns
        $excludePassiveRegex = '/\b(kadın|yarın|altın|bütün|yakın|kendin|senin|onun|bizim|sizin|onların|kimin|benim|bugün|dün|oyun|sorun|uzun|kalın|serin|derin|gün|yön|son|on|bun|şun|gül|kıl|bil|sil|bul|yan|dön|bin|in|sön|yen|sun|kan|yıl|inan|uyan|dayan|kullan|davran|öğren|beğen|güven|düşün)(?:dı|di|du|dü|tı|ti|tu|tü|mış|miş|muş|müş)(?:dır|dir|dur|dür|tır|tir|tur|tür)?\b/ui';

        foreach ($sentences as $index => $sentence) {
            if (preg_match($passiveRegexDoc, $sentence) && !preg_match($excludePassiveRegex, $sentence)) {
                $passiveSentencesCount++;
                $passiveSentenceIndexes[] = $index;
            }
        }

        $passiveVoicePercentage = $sentenceCount > 0 ? ($passiveSentencesCount / $sentenceCount) * 100 : 0;
        $passiveFeedback = $this->getPassiveVoiceFeedback($passiveVoicePercentage);

        return [
            "flesch_reading_ease" => round($fre, 2),
            "flesch_kincaid_grade" => round($fkgl, 2),
            "atesman_index" => round($atesman, 2),
            "atesman_feedback" => $atesmanFeedback,
            "gunning_fog_index" => round($gunningFog, 2),
            "complex_polysyllabic_words_count" => $complexWordsCount,
            "complex_words_percentage" => round($complexRatio, 2),
            "complex_words_feedback" => $complexFeedback,
            "transition_words" => [
                "matched_count" => $transitionMatches,
                "sentences_with_transitions_count" => $sentencesWithTransitions,
                "transition_sentence_ratio_percentage" => round($transitionRatio, 2),
                "feedback" => $transitionFeedback,
                "target_met" => $transitionRatio >= 15 && $transitionRatio <= 30
            ],
            "passive_voice" => [
                "passive_sentences_count" => $passiveSentencesCount,
                "passive_voice_percentage" => round($passiveVoicePercentage, 2),
                "feedback" => $passiveFeedback,
                "target_met" => $passiveVoicePercentage <= 15,
                "passive_sentence_indexes" => $passiveSentenceIndexes
            ]
        ];
    }

    private function getAtesmanFeedback(float $score): array {
        if ($score >= 70) {
            return ["status" => "success", "label" => "Çok Kolay", "advice" => "Oldukça Akıcı: Metniniz son derece sade ve anlaşılır. Her yaştan okuyucu tarafından kolayca okunabilir."];
        } elseif ($score >= 50) {
            return ["status" => "success", "label" => "İdeal Web SEO", "advice" => "İdeal Denge: Mükemmel! İnternet okuyucuları için en verimli aralıkta; okuyucuyu yormayan bir akıcılığa sahip."];
        } elseif ($score >= 35) {
            return ["status" => "warning", "label" => "Orta / Ağır", "advice" => "Biraz Dikkat Gerektiriyor: Puanın orta çıkmasının nedeni uzun cümleler (20+ kelime) veya çok heceli kelimelerin sık kullanımı olabilir. Uzun cümleleri bölüp kelimeleri sadeleştirerek akıcılığı artırabilirsiniz."];
        } else {
            return ["status" => "danger", "label" => "Zor / Yoğun Metin", "advice" => "Metin Ağır ve Yorucu: Çok heceli kelime yoğunluğu yüksek ve/veya cümleler fazla uzun. Okuyucunun sayfayı terk etmemesi için cümleleri kısaltıp daha yalın sözcükler kullanmalısınız."];
        }
    }

    private function getComplexWordsFeedback(float $ratio): array {
        if ($ratio < 30) {
            return ["status" => "success", "label" => "Yalın", "advice" => "Yalın Kelime Seçimi: Harika! Sözcükleriniz kısa, net ve anlaşılır; okuyucunun zihninde ağırlık yaratmıyor."];
        } elseif ($ratio <= 45) {
            return ["status" => "success", "label" => "Dengeli", "advice" => "Dengeli Kelime Dağarcığı: Uzmanlık terimleri ile günlük anlaşılır dil tam dengede; metin hem profesyonel hem akıcı."];
        } elseif ($ratio <= 60) {
            return ["status" => "warning", "label" => "Ağır Kelimeler", "advice" => "Uzun Ekler ve Ağır Kelimeler: Çok heceli ve uzun ek almış sözcükler fazla. Bunları daha pratik karşılıklarıyla (örn. 'gerçekleştirebilmekteyiz' yerine 'yapıyoruz') değiştirebilirsiniz."];
        } else {
            return ["status" => "danger", "label" => "Okuması Zor", "advice" => "Kelime Seçimi Yorucu: Metnin büyük kısmı 3 ve üzeri heceli uzun kelimelerden oluşuyor. Ağır ifadeleri sadeleştirerek (örn. 'faydalanabilmeniz mümkündür' yerine 'yararlanabilirsiniz') metni hafifletin."];
        }
    }

    private function getTransitionWordsFeedback(float $ratio): array {
        if ($ratio > 30) {
            return ["status" => "warning", "label" => "Aşırı Yoğun", "advice" => "Bağlaç Yoğunluğu Fazla: Geçiş kelimeleri biraz sık tekrar edilmiş. Cümle başlarındaki gereksiz bağlaçları azaltarak anlatımı daha doğrudan hale getirebilirsiniz."];
        } elseif ($ratio >= 15) {
            return ["status" => "success", "label" => "İdeal Akıcılık", "advice" => "Kusursuz Bağlantılar: Harika! Cümleler birbirine 'çünkü', 'örneğin', 'bu nedenle' gibi doğal köprülerle bağlanarak su gibi akan bir ritim yakalamış."];
        } elseif ($ratio >= 10) {
            return ["status" => "warning", "label" => "Geliştirilebilir", "advice" => "Bağlantılar Artırılabilir: Cümleler arası mantıksal bağı güçlendirmek için paragraflara 'Örneğin', 'Ayrıca', 'Bu doğrultuda' gibi geçiş kelimeleri serpiştirebilirsiniz."];
        } else {
            return ["status" => "danger", "label" => "Kopuk Akış", "advice" => "Cümleler Birbirinden Kopuk: Metin liste gibi peş peşe sıralanmış duruyor (İdeal: %15-%30). Düşünce akışını birbirine bağlamak için 'Çünkü', 'Örneğin', 'Bu nedenle', 'Ancak', 'Dolayısıyla' gibi bağlaçlar ekleyin."];
        }
    }

    private function getPassiveVoiceFeedback(float $ratio): array {
        if ($ratio <= 15) {
            return ["status" => "success", "label" => "İdeal / Başarılı", "advice" => "Canlı ve İkna Edici: Mükemmel! Doğrudan okuyucuya seslenen, aktif ve eyleme geçiren güçlü bir anlatım kullanılmış."];
        } elseif ($ratio <= 25) {
            return ["status" => "warning", "label" => "Orta / Geliştirilmeli", "advice" => "Daha Doğrudan Olabilir: Bazı cümlelerde edilgen fiiller kullanılmış. Cümleleri özne odaklı aktif ifadelere çevirerek anlatımı güçlendirebilirsiniz."];
        } else {
            return ["status" => "danger", "label" => "Ağır / Resmi Dil", "advice" => "Resmi ve Edilgen Anlatım: Metin resmi evrak gibi çok fazla edilgen çatı ('-il, -in, -ılmıştır') içeriyor. Canlı fiiller tercih edin."];
        }
    }
}

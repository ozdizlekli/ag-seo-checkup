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
        $transitionRegex = '/\b(çünkü|bu nedenle|örneğin|ancak|buna ek olarak|son olarak|öte yandan|dolayısıyla|ayrıca|bununla birlikte)\b/ui';
        
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
        $passiveRegexDoc = '/\b\p{L}+(ıl|il|ul|ül|ın|in|un|ün|n)dı|\b\p{L}+(ıl|il|ul|ül|ın|in|un|ün|n)mıştır/ui';
        $passiveSentencesCount = 0;
        $passiveSentenceIndexes = [];
        
        foreach ($sentences as $index => $sentence) {
            if (preg_match($passiveRegexDoc, $sentence)) {
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
                "target_met" => $transitionRatio >= 25 && $transitionRatio <= 35
            ],
            "passive_voice" => [
                "passive_sentences_count" => $passiveSentencesCount,
                "passive_voice_percentage" => round($passiveVoicePercentage, 2),
                "feedback" => $passiveFeedback,
                "target_met" => $passiveVoicePercentage <= 10,
                "passive_sentence_indexes" => $passiveSentenceIndexes
            ]
        ];
    }

    private function getAtesmanFeedback(float $score): array {
        if ($score >= 80) {
            return ["status" => "success", "label" => "Çok Kolay", "advice" => "Oldukça Akıcı: Metniniz son derece sade ve anlaşılır. Her yaştan okuyucu tarafından tek nefeste kolayca okunabilir."];
        } elseif ($score >= 60) {
            return ["status" => "success", "label" => "İdeal Web SEO", "advice" => "İdeal Denge: Mükemmel! İnternet okuyucuları için en verimli aralıkta; hem bilgilendirici hem de yormayan bir akıcılığa sahip."];
        } elseif ($score >= 45) {
            return ["status" => "warning", "label" => "Orta / Ağır", "advice" => "Biraz Dikkat Gerektiriyor: Cümleler genel olarak uzun. 20 kelimeyi aşan uzun cümleleri ikiye bölerek (örn. '...yaptığından dolayı şöyle oldu' yerine '...yaptı. Bu yüzden şöyle oldu') okumayı kolaylaştırabilirsiniz."];
        } else {
            return ["status" => "danger", "label" => "Zor / Yoğun Metin", "advice" => "Cümleler Ağır ve Uzun: Metin takip etmesi güç ve yoğun bir dille yazılmış. Okuyucunun sıkılıp sayfayı terk etmemesi için uzun cümleleri kısaltıp daha doğrudan ifadeler kullanmanız önerilir."];
        }
    }

    private function getComplexWordsFeedback(float $ratio): array {
        if ($ratio < 20) {
            return ["status" => "success", "label" => "Yalın", "advice" => "Yalın Kelime Seçimi: Harika! Sözcükleriniz kısa, net ve anlaşılır; okuyucunun zihninde ağırlık yaratmıyor."];
        } elseif ($ratio <= 35) {
            return ["status" => "success", "label" => "Dengeli", "advice" => "Dengeli Kelime Dağarcığı: Uzmanlık terimleri ile günlük anlaşılır dil tam dengede; metin hem profesyonel hem akıcı."];
        } elseif ($ratio <= 50) {
            return ["status" => "warning", "label" => "Ağır Kelimeler", "advice" => "Uzun Ekler ve Ağır Kelimeler: Çok heceli ve uzun ek almış sözcükler fazla. Bunları daha pratik karşılıklarıyla değiştirin (örn. 'gerçekleştirebilmekteyiz' yerine 'yapıyoruz', 'sağlayabilmektedir' yerine 'sağlar')."];
        } else {
            return ["status" => "danger", "label" => "Okuması Zor", "advice" => "Kelime Seçimi Yorucu: Metnin büyük kısmı 3 ve üzeri heceli uzun kelimelerden oluşuyor. Ağır ifadeleri sadeleştirerek (örn. 'faydalanabilmeniz mümkündür' yerine 'yararlanabilirsiniz') metni hafifletin."];
        }
    }

    private function getTransitionWordsFeedback(float $ratio): array {
        if ($ratio > 35) {
            return ["status" => "warning", "label" => "Aşırı Yoğun", "advice" => "Bağlaç Yoğunluğu Fazla: Geçiş kelimeleri biraz sık tekrar edilmiş. Cümle başlarındaki gereksiz bağlaçları azaltarak anlatımı daha doğrudan hale getirebilirsiniz."];
        } elseif ($ratio >= 25) {
            return ["status" => "success", "label" => "İdeal Akıcılık", "advice" => "Kusursuz Bağlantılar: Harika! Cümleler birbirine 'çünkü', 'örneğin', 'bu nedenle' gibi doğal köprülerle bağlanarak su gibi akan bir ritim yakalamış."];
        } elseif ($ratio >= 15) {
            return ["status" => "warning", "label" => "Geliştirilebilir", "advice" => "Bağlantılar Artırılabilir: Cümleler arası mantıksal bağı güçlendirmek için paragraflara 'Örneğin', 'Ayrıca', 'Bu doğrultuda' gibi geçiş kelimeleri serpiştirebilirsiniz."];
        } else {
            return ["status" => "danger", "label" => "Kopuk Akış", "advice" => "Cümleler Birbirinden Kopuk: Metin liste gibi peş peşe sıralanmış duruyor (İdeal: %25-%35). Düşünce akışını birbirine bağlamak için 'Çünkü', 'Örneğin', 'Bu nedenle', 'Ancak', 'Dolayısıyla' gibi bağlaçlar ekleyin."];
        }
    }

    private function getPassiveVoiceFeedback(float $ratio): array {
        if ($ratio <= 10) {
            return ["status" => "success", "label" => "Canlı ve Dinamik", "advice" => "Canlı ve İkna Edici: Mükemmel! Doğrudan okuyucuya seslenen, aktif ve eyleme geçiren güçlü bir anlatım kullanılmış."];
        } elseif ($ratio <= 20) {
            return ["status" => "warning", "label" => "Hafif Edilgen", "advice" => "Daha Doğrudan Olabilir: Bazı cümlelerde edilgen fiiller kullanılmış. Cümleleri özne odaklı aktif ifadelere çevirin (örn. 'Analizler uzmanlarca yapılır' yerine 'Uzmanlar analiz yapar')."];
        } else {
            return ["status" => "danger", "label" => "Resmi Dil", "advice" => "Resmi ve Edilgen Anlatım: Metin resmi evrak gibi çok fazla '-il, -in, -ılmıştır' eki içeriyor. 'Hedeflenmektedir / tamamlanır' yerine 'hedefliyoruz / tamamlar' gibi canlı fiiller tercih edin."];
        }
    }
}

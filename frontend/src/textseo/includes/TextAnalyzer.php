<?php

require_once __DIR__ . '/../config.php';

class TextAnalyzer {

    /**
     * Web sayfası metinlerini SEO açısından analiz eder.
     * 
     * @param array $scrapedData Scraper sınıfından gelen veri
     * @param array $keywords Odak ve yan anahtar kelimeler
     * @return array
     */
    public function analyze(array $scrapedData, array $keywords = []): array {
        mb_internal_encoding('UTF-8');
        
        $titleAnalysis = $this->analyzeTitle($scrapedData['title'] ?? '', $keywords);
        $descAnalysis = $this->analyzeDescription($scrapedData['description'] ?? '', $keywords);
        $headingAnalysis = $this->analyzeHeadings($scrapedData['headings'] ?? [], $keywords);
        
        $bodyText = $scrapedData['body_text'] ?? '';
        $wordCount = isset($scrapedData['word_count']) ? (int)$scrapedData['word_count'] : 0;
        
        $keywordAnalysis = $this->analyzeKeywords($bodyText, $wordCount, $keywords);
        $contentAnalysis = $this->analyzeContent($bodyText, $wordCount);
        
        $deficiencyReport = $this->generateDeficiencyReport(
            $titleAnalysis,
            $descAnalysis,
            $headingAnalysis,
            $keywordAnalysis,
            $contentAnalysis
        );
        
        return [
            'title'             => $titleAnalysis,
            'description'       => $descAnalysis,
            'headings'          => $headingAnalysis,
            'keywords'          => $keywordAnalysis,
            'content'           => $contentAnalysis,
            'deficiency_report' => $deficiencyReport
        ];
    }

    /**
     * Title etiketini analiz eder.
     */
    private function analyzeTitle(string $title, array $keywords): array {
        $length = mb_strlen($title);
        $status = 'kısa';
        
        $min = defined('SEO_TITLE_MIN') ? SEO_TITLE_MIN : 50;
        $max = defined('SEO_TITLE_MAX') ? SEO_TITLE_MAX : 60;
        
        if ($length > $max) {
            $status = 'uzun';
        } elseif ($length >= $min && $length <= $max) {
            $status = 'ideal';
        }
        
        $hasFocusKeyword = false;
        $keywordAtStart = false;
        
        if (!empty($keywords['focus'])) {
            $focus = $keywords['focus'];
            $pos = mb_stripos($title, $focus);
            if ($pos !== false) {
                $hasFocusKeyword = true;
                if ($pos <= 30) {
                    $keywordAtStart = true;
                }
            }
        }
        
        return [
            'length' => $length,
            'status' => $status,
            'has_focus_keyword' => $hasFocusKeyword,
            'keyword_at_start' => $keywordAtStart
        ];
    }

    /**
     * Meta description etiketini analiz eder.
     */
    private function analyzeDescription(string $description, array $keywords): array {
        $length = mb_strlen($description);
        $status = 'kısa';
        
        $min = defined('SEO_DESC_MIN') ? SEO_DESC_MIN : 150;
        $max = defined('SEO_DESC_MAX') ? SEO_DESC_MAX : 160;
        
        if ($length > $max) {
            $status = 'uzun';
        } elseif ($length >= $min && $length <= $max) {
            $status = 'ideal';
        }
        
        $hasFocusKeyword = false;
        if (!empty($keywords['focus'])) {
            $focus = $keywords['focus'];
            if (mb_stripos($description, $focus) !== false) {
                $hasFocusKeyword = true;
            }
        }
        
        $hasCta = false;
        $ctaWords = [
            'hemen', 'keşfet', 'keşfedin', 'öğren', 'öğrenin', 'incele', 'inceleyin',
            'başla', 'başlayın', 'dene', 'deneyin', 'alın', 'okuyun', 'bulun',
            'karşılaştır', 'karşılaştırın', 'ücretsiz', 'fırsat', 'şimdi',
            'tıkla', 'tıklayın', 'göz atın', 'kaçırma', 'kaçırmayın'
        ];
        
        foreach ($ctaWords as $cta) {
            $escapedCta = preg_quote($cta, '/');
            // Türkçe uyumlu kelime sınırı: Unicode harf/rakam olmayan karakterlerle çevrelenmeli
            if (preg_match('/(?<![\p{L}\p{N}])' . $escapedCta . '(?![\p{L}\p{N}])/iu', $description)) {
                $hasCta = true;
                break;
            }
        }
        
        return [
            'length' => $length,
            'status' => $status,
            'has_focus_keyword' => $hasFocusKeyword,
            'has_cta' => $hasCta
        ];
    }

    /**
     * Başlık etiketlerini (H1, H2, vb.) analiz eder.
     */
    private function analyzeHeadings(array $headings, array $keywords): array {
        $h1Count = 0;
        $h2Count = 0;
        $h3Count = 0;
        
        $hierarchyValid = true;
        $hierarchyIssues = [];
        
        $keywordsInHeadings = [
            'h1' => false,
            'h2_list' => [],
            'h3_list' => []
        ];
        
        $focus = !empty($keywords['focus']) ? $keywords['focus'] : null;
        
        foreach ($headings as $heading) {
            $tag = strtolower($heading['tag'] ?? '');
            $text = $heading['text'] ?? '';
            
            $hasKeyword = false;
            if ($focus !== null && mb_stripos($text, $focus) !== false) {
                $hasKeyword = true;
            }
            
            if ($tag === 'h1') {
                $h1Count++;
                if ($hasKeyword) {
                    $keywordsInHeadings['h1'] = true;
                }
            } elseif ($tag === 'h2') {
                $h2Count++;
                $keywordsInHeadings['h2_list'][] = $hasKeyword;
                
                if ($h1Count === 0) {
                    $hierarchyValid = false;
                    $issue = "Başlık hiyerarşisinde sorun var: H1 olmadan H2 kullanılmış.";
                    if (!in_array($issue, $hierarchyIssues)) {
                        $hierarchyIssues[] = $issue;
                    }
                }
            } elseif ($tag === 'h3') {
                $h3Count++;
                $keywordsInHeadings['h3_list'][] = $hasKeyword;
                
                if ($h2Count === 0) {
                    $hierarchyValid = false;
                    $issue = "Başlık hiyerarşisinde sorun var: H2 olmadan H3 kullanılmış.";
                    if (!in_array($issue, $hierarchyIssues)) {
                        $hierarchyIssues[] = $issue;
                    }
                }
            }
        }
        
        return [
            'h1_count'             => $h1Count,
            'h2_count'             => $h2Count,
            'h3_count'             => $h3Count,
            'hierarchy_valid'      => $hierarchyValid,
            'hierarchy_issues'     => $hierarchyIssues,
            'keywords_in_headings' => $keywordsInHeadings
        ];
    }

    /**
     * Anahtar kelime yoğunluğunu analiz eder.
     */
    private function analyzeKeywords(string $bodyText, int $wordCount, array $keywords): array {
        if (empty($keywords['focus']) && empty($keywords['secondary'])) {
            return ['focus' => null, 'secondary' => [], 'missing' => []];
        }
        
        $focusResult = null;
        $missing = [];
        $secondaryResults = [];
        
        // Edge case: kelime sayısı 0 olmasın (sıfıra bölme hatası)
        $wordCountSafe = max(1, $wordCount);

        if (!empty($keywords['focus'])) {
            $focus = $keywords['focus'];
            $escapedFocus = preg_quote($focus, '/');
            
            // Türkçe uyumu için \b yerine regex harf ve sayı sınırlarını kullanıyoruz.
            // Bu sayede "influencer" veya "blog" gibi İngilizce terimler de, Türkçe karakterler de doğru algılanır.
            $pattern = '/(?<![\p{L}\p{N}_])' . $escapedFocus . '(?![\p{L}\p{N}_])/iu';
            
            $count = preg_match_all($pattern, $bodyText);
            
            $density = ($count / $wordCountSafe) * 100;
            
            $status = 'ideal';
            if ($density < 0.5) {
                $status = 'düşük';
            } elseif ($density > 2.5) {
                $status = 'yüksek';
            }
            
            $focusResult = [
                'keyword' => $focus,
                'density' => round($density, 2),
                'count'   => $count,
                'status'  => $status
            ];
            
            if ($count === 0) {
                $missing[] = $focus;
            }
        }
        
        if (!empty($keywords['secondary']) && is_array($keywords['secondary'])) {
            foreach ($keywords['secondary'] as $sec) {
                $escapedSec = preg_quote($sec, '/');
                $pattern = '/(?<![\p{L}\p{N}_])' . $escapedSec . '(?![\p{L}\p{N}_])/iu';
                $count = preg_match_all($pattern, $bodyText);
                
                $density = ($count / $wordCountSafe) * 100;
                $secondaryResults[] = [
                    'keyword' => $sec,
                    'density' => round($density, 2),
                    'count'   => $count
                ];
                
                if ($count === 0) {
                    $missing[] = $sec;
                }
            }
        }
        
        return [
            'focus'     => $focusResult,
            'secondary' => $secondaryResults,
            'missing'   => $missing
        ];
    }

    /**
     * Metnin okunabilirliğini ve yapısal analizini yapar (Türkçe uyumlu).
     */
    private function analyzeContent(string $bodyText, int $wordCount): array {
        // Noktalama işaretlerine göre cümlelere böl (boş olanları filtrele)
        $sentences = preg_split('/[.!?]+/u', $bodyText, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter(array_map('trim', $sentences));
        $sentenceCount = count($sentences);
        
        // Edge case korumaları
        $sentenceCountSafe = max(1, $sentenceCount);
        $wordCountSafe = max(1, $wordCount);
        
        $avgSentenceLength = $wordCount / $sentenceCountSafe;
        
        $sentenceStatus = 'ideal';
        if ($avgSentenceLength < 10) {
            $sentenceStatus = 'kısa';
        } elseif ($avgSentenceLength > 20) {
            $sentenceStatus = 'uzun';
        }
        
        // Paragraf sayısı (boş satırlarla ayrılmış bloklar)
        $paragraphs = preg_split('/\n\s*\n/u', $bodyText, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphCount = count(array_filter(array_map('trim', $paragraphs)));
        
        // Okunabilirlik skoru (Türkçe - Ateşman Formülü)
        // Türkçe sesli harfler sayılır (Toplam Hece)
        $syllableCount = 0;
        if (preg_match_all('/[aeıioöuüAEIİOÖUÜ]/u', $bodyText, $matches)) {
            $syllableCount = count($matches[0]);
        }
        
        $avgSyllablePerWord = $syllableCount / $wordCountSafe;
        $avgWordPerSentence = $avgSentenceLength;
        
        $atesmanScore = 198.825 - (40.175 * $avgSyllablePerWord) - (2.610 * $avgWordPerSentence);
        
        // İçerik tamamen boşsa değerleri resetle
        if ($wordCount === 0) {
            $atesmanScore = 0;
            $avgSentenceLength = 0;
            $avgSyllablePerWord = 0;
            $avgWordPerSentence = 0;
            $sentenceStatus = 'kısa';
        }
        
        $atesmanScore = max(0, min(100, $atesmanScore));
        
        $readabilityLabel = '';
        if ($atesmanScore >= 90) {
            $readabilityLabel = 'Çok Kolay';
        } elseif ($atesmanScore >= 70) {
            $readabilityLabel = 'Kolay (Web için ideal)';
        } elseif ($atesmanScore >= 50) {
            $readabilityLabel = 'Orta';
        } elseif ($atesmanScore >= 30) {
            $readabilityLabel = 'Zor';
        } else {
            $readabilityLabel = 'Çok Zor';
        }
        
        return [
            'word_count'             => $wordCount,
            'sentence_count'         => $sentenceCount,
            'avg_sentence_length'    => round($avgSentenceLength, 2),
            'sentence_status'        => $sentenceStatus,
            'paragraph_count'        => $paragraphCount,
            'readability_score'      => round($atesmanScore, 2),
            'readability_label'      => $readabilityLabel,
            'avg_syllable_per_word'  => round($avgSyllablePerWord, 2),
            'avg_word_per_sentence'  => round($avgWordPerSentence, 2)
        ];
    }

    /**
     * Tüm analizlerden yola çıkarak Gemini için somut ve tek satırlık bir eksiklik raporu üretir.
     */
    private function generateDeficiencyReport(
        array $titleAnalysis, 
        array $descAnalysis, 
        array $headingAnalysis, 
        array $keywordAnalysis, 
        array $contentAnalysis
    ): string {
        $issues = [];
        
        // 1. Title Sorunları
        $minTitle = defined('SEO_TITLE_MIN') ? SEO_TITLE_MIN : 50;
        $maxTitle = defined('SEO_TITLE_MAX') ? SEO_TITLE_MAX : 60;
        
        if ($titleAnalysis['length'] === 0) {
            $issues[] = "• Title eksik (0 karakter); {$minTitle}-{$maxTitle} karakter arası olmalı, mutlaka eklenmeli.";
        } elseif ($titleAnalysis['status'] === 'kısa') {
            $issues[] = "• Title {$titleAnalysis['length']} karakter; {$minTitle}-{$maxTitle} arası olmalı, uzatılmalı.";
        } elseif ($titleAnalysis['status'] === 'uzun') {
            $issues[] = "• Title {$titleAnalysis['length']} karakter; {$minTitle}-{$maxTitle} arası olmalı, kısaltılmalı.";
        }
        
        if (!empty($keywordAnalysis['focus']) && $keywordAnalysis['focus'] !== null) {
            if (!$titleAnalysis['has_focus_keyword']) {
                $issues[] = "• Odak anahtar kelime title'da bulunmuyor, eklenmeli.";
            } elseif (!$titleAnalysis['keyword_at_start']) {
                $issues[] = "• Odak anahtar kelime title'ın başında değil, öne alınmalı.";
            }
        }
        
        // 2. Description Sorunları
        $minDesc = defined('SEO_DESC_MIN') ? SEO_DESC_MIN : 150;
        $maxDesc = defined('SEO_DESC_MAX') ? SEO_DESC_MAX : 160;
        
        if ($descAnalysis['length'] === 0) {
            $issues[] = "• Meta description eksik (0 karakter); {$minDesc}-{$maxDesc} karakter arası yazılmalı.";
        } elseif ($descAnalysis['status'] === 'kısa') {
            $issues[] = "• Meta description {$descAnalysis['length']} karakter; {$minDesc}-{$maxDesc} arası olmalı, uzatılmalı.";
        } elseif ($descAnalysis['status'] === 'uzun') {
            $issues[] = "• Meta description {$descAnalysis['length']} karakter; {$minDesc}-{$maxDesc} arası olmalı, kısaltılmalı.";
        }
        
        if (!empty($keywordAnalysis['focus']) && $keywordAnalysis['focus'] !== null && !$descAnalysis['has_focus_keyword']) {
            $issues[] = "• Odak anahtar kelime meta description'da bulunmuyor, eklenmeli.";
        }
        if (!$descAnalysis['has_cta']) {
            $issues[] = "• Meta description'da aksiyon çağrısı (CTA) kelimesi yok, eklenmeli.";
        }
        
        // 3. Heading Sorunları
        if ($headingAnalysis['h1_count'] === 0) {
            $issues[] = "• H1 etiketi eksik, mutlaka eklenmeli.";
        } elseif ($headingAnalysis['h1_count'] > 1) {
            $issues[] = "• Sayfada {$headingAnalysis['h1_count']} adet H1 var; tek H1 olmalı.";
        }
        
        if (!$headingAnalysis['hierarchy_valid']) {
            foreach ($headingAnalysis['hierarchy_issues'] as $issue) {
                $issues[] = "• {$issue}";
            }
        }
        
        // 4. Keyword Sorunları
        if (isset($keywordAnalysis['focus']) && $keywordAnalysis['focus'] !== null) {
            if ($keywordAnalysis['focus']['status'] === 'düşük') {
                $issues[] = "• Anahtar kelime yoğunluğu %{$keywordAnalysis['focus']['density']}; %0.5-2.5 arasına çıkarılmalı.";
            } elseif ($keywordAnalysis['focus']['status'] === 'yüksek') {
                $issues[] = "• Anahtar kelime yoğunluğu %{$keywordAnalysis['focus']['density']}; %0.5-2.5 arasına çekilmeli.";
            }
        }
        
        if (!empty($keywordAnalysis['missing'])) {
            foreach ($keywordAnalysis['missing'] as $missed) {
                if (isset($keywordAnalysis['focus']) && $keywordAnalysis['focus'] !== null && $missed === $keywordAnalysis['focus']['keyword']) {
                    // Odak kelimesi zaten 'düşük' uyarısı vereceği için burada es geçiyoruz
                } else {
                    $issues[] = "• '{$missed}' yan anahtar kelimesi metinde hiç geçmiyor, içeriğe eklenmeli.";
                }
            }
        }
        
        // 5. Content Sorunları
        if ($contentAnalysis['word_count'] === 0) {
             $issues[] = "• Sayfada içerik metni bulunamadı, SEO için yeterli uzunlukta metin eklenmeli.";
        } else {
            if ($contentAnalysis['sentence_status'] === 'uzun') {
                $issues[] = "• Ortalama cümle uzunluğu {$contentAnalysis['avg_sentence_length']} kelime; 10-20 kelime arası ideal.";
            } elseif ($contentAnalysis['sentence_status'] === 'kısa') {
                $issues[] = "• Ortalama cümle uzunluğu {$contentAnalysis['avg_sentence_length']} kelime; 10-20 kelime arası ideal.";
            }
            
            if ($contentAnalysis['readability_score'] < 50) {
                $issues[] = "• Okunabilirlik puanı {$contentAnalysis['readability_score']} ({$contentAnalysis['readability_label']}); web içeriği için 50+ hedeflenmeli.";
            }
        }
        
        if (empty($issues)) {
            return "Ciddi bir eksiklik tespit edilmedi.";
        }
        
        return implode("\n", $issues);
    }
}

<?php

namespace SeoEngine;

class IntentEngagementAnalyzer {
    private TextCleaner $cleaner;

    public function __construct(TextCleaner $cleaner) {
        $this->cleaner = $cleaner;
    }

    public function analyze(): array {
        $sentences = $this->cleaner->getSentences();
        $words = $this->cleaner->getWords();
        $wordCount = count($words);
        $sentenceCount = count($sentences);

        // CTA
        $ctaRegex = '/\b(hemen tıklayın|keşfedin|satın alın|deneyin|inceleyin|bize ulaşın|indirin)\b/ui';
        $totalCtaMatches = 0;
        $ctaQuarters = ["q1" => 0, "q2" => 0, "q3" => 0, "q4" => 0];

        foreach ($sentences as $idx => $s) {
            $count = preg_match_all($ctaRegex, $s, $m);
            if ($count > 0) {
                $totalCtaMatches += $count;
                $quarter = ceil((($idx + 1) / max(1, $sentenceCount)) * 4);
                $ctaQuarters["q{$quarter}"] += $count;
            }
        }
        
        $hasClosingCta = $ctaQuarters["q4"] > 0;

        // Power Words
        $powerWordsList = ["kanıtlanmış", "eksiksiz", "devrim niteliğinde", "adım adım", "kesin", "garanti", "profesyonel", "kritik", "gizli", "ücretsiz", "kapsamlı", "başarılı", "ipuçları", "önemlidir"];
        $powerWordsCount = 0;
        $powerWordsSample = [];
        $cleanTextLower = TextCleaner::trToLower($this->cleaner->getCleanText());
        
        foreach ($powerWordsList as $pw) {
            $count = mb_substr_count($cleanTextLower, $pw);
            if ($count > 0) {
                $powerWordsCount += $count;
                if (!in_array($pw, $powerWordsSample)) {
                    $powerWordsSample[] = $pw;
                }
            }
        }
        $powerWordsRatio = $wordCount > 0 ? ($powerWordsCount / $wordCount) * 100 : 0;

        // Hedging vs Certainty
        $hedgingRegex = '/\b(belki|sanırım|olabilir|muhtemelen|gibi görünüyor|bazen|ihtimalle)\b/ui';
        $certaintyRegex = '/\b(kesinlikle|kanıtlanmıştır|gösterir|açıktır|doğrudan|daima|esastır)\b/ui';
        
        $hedgingCount = 0;
        $certaintyCount = 0;

        foreach ($sentences as $s) {
            if (preg_match($hedgingRegex, $s)) $hedgingCount++;
            if (preg_match($certaintyRegex, $s)) $certaintyCount++;
        }

        $hedgingRatio = $sentenceCount > 0 ? ($hedgingCount / $sentenceCount) * 100 : 0;
        $certaintyRatio = $sentenceCount > 0 ? ($certaintyCount / $sentenceCount) * 100 : 0;

        $tone = "NEUTRAL";
        if ($certaintyRatio > 15 && $hedgingRatio < 5) {
            $tone = "AUTHORITATIVE";
        } elseif ($hedgingRatio >= 5) {
            $tone = "HESITANT";
        }

        return [
            "cta_metrics" => [
                "total_cta_matches" => $totalCtaMatches,
                "placement_quarters" => $ctaQuarters,
                "has_closing_cta" => $hasClosingCta
            ],
            "power_words" => [
                "matched_count" => $powerWordsCount,
                "power_words_ratio_percentage" => round($powerWordsRatio, 2),
                "matched_words_sample" => array_slice($powerWordsSample, 0, 5)
            ],
            "modality_and_tone" => [
                "hedging_words_count" => $hedgingCount,
                "hedging_ratio_percentage" => round($hedgingRatio, 2),
                "certainty_words_count" => $certaintyCount,
                "certainty_ratio_percentage" => round($certaintyRatio, 2),
                "tone_classification" => $tone
            ]
        ];
    }
}

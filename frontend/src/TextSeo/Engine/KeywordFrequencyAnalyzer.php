<?php

namespace SeoEngine;

class KeywordFrequencyAnalyzer {
    private TextCleaner $cleaner;
    private ?string $targetKeyword;
    private array $secondaryKeywords;

    public function __construct(TextCleaner $cleaner, ?string $targetKeyword = null, array $secondaryKeywords = []) {
        $this->cleaner = $cleaner;
        $this->targetKeyword = $targetKeyword ? TextCleaner::trToLower($targetKeyword) : null;
        $this->secondaryKeywords = array_map(fn($k) => TextCleaner::trToLower($k), $secondaryKeywords);
    }

    public function analyze(): array {
        $words = array_map(fn($w) => TextCleaner::trToLower($w), $this->cleaner->getWords());
        $cleanText = TextCleaner::trToLower($this->cleaner->getCleanText());
        $wordCount = count($words);

        $stopwords = ["ve", "veya", "ama", "fakat", "lakin", "ancak", "için", "ile", "de", "da", "ki", "bir", "bu", "şu", "o", "mı", "mi", "mu", "mü", "çok", "daha", "en", "kadar", "gibi", "göre", "dolayı", "rağmen", "karşın", "yerine", "hakkında", "dair", "ait"];
        $filteredWords = array_filter($words, fn($w) => !in_array($w, $stopwords));
        $filteredWords = array_values($filteredWords);

        // N-grams
        $unigrams = array_count_values($filteredWords);
        arsort($unigrams);
        $topUnigrams = [];
        $i = 0;
        foreach ($unigrams as $term => $c) {
            if ($i++ >= 5) break;
            $topUnigrams[] = ["term" => $term, "count" => $c, "density" => $wordCount > 0 ? round(($c / $wordCount) * 100, 2) : 0];
        }

        $bigrams = [];
        for ($j = 0; $j < count($filteredWords) - 1; $j++) {
            $bg = $filteredWords[$j] . ' ' . $filteredWords[$j + 1];
            $bigrams[$bg] = ($bigrams[$bg] ?? 0) + 1;
        }
        arsort($bigrams);
        $topBigrams = [];
        $i = 0;
        foreach ($bigrams as $term => $c) {
            if ($i++ >= 4) break;
            $topBigrams[] = ["term" => $term, "count" => $c, "density" => $wordCount > 0 ? round(($c * 2 / $wordCount) * 100, 2) : 0];
        }

        $trigrams = [];
        for ($j = 0; $j < count($filteredWords) - 2; $j++) {
            $tg = $filteredWords[$j] . ' ' . $filteredWords[$j + 1] . ' ' . $filteredWords[$j + 2];
            $trigrams[$tg] = ($trigrams[$tg] ?? 0) + 1;
        }
        arsort($trigrams);
        $topTrigrams = [];
        $i = 0;
        foreach ($trigrams as $term => $c) {
            if ($i++ >= 3) break;
            $topTrigrams[] = ["term" => $term, "count" => $c, "density" => $wordCount > 0 ? round(($c * 3 / $wordCount) * 100, 2) : 0];
        }

        // Target Keyword Metrics
        $targetMetrics = [];
        if ($this->targetKeyword) {
            $kwWordCount = count(explode(' ', $this->targetKeyword));
            $exactCount = mb_substr_count($cleanText, $this->targetKeyword);
            
            $firstWord = explode(' ', $this->targetKeyword)[0];
            $stemLength = mb_strlen($firstWord, 'UTF-8');
            $stem = mb_substr($firstWord, 0, max(4, $stemLength - 2), 'UTF-8');
            
            preg_match_all('/\b' . preg_quote($stem, '/') . '\p{L}*\b/ui', $cleanText, $stemMatches);
            $totalMentions = count($stemMatches[0] ?? []);
            $totalMentions = max($exactCount, $totalMentions);
            $inflectedCount = max(0, $totalMentions - $exactCount);
            
            $inflectionRatio = $totalMentions > 0 ? $inflectedCount / $totalMentions : 0;
            $density = $wordCount > 0 ? ($exactCount * $kwWordCount / $wordCount) * 100 : 0;
            
            $minDensity = 1.0;
            $deficit = 0;
            if ($density < $minDensity) {
                $targetMentions = ceil(($minDensity / 100) * $wordCount / max(1, $kwWordCount));
                $deficit = max(0, $targetMentions - $exactCount);
            }

            $targetMetrics = [
                "keyword" => $this->targetKeyword,
                "exact_matches_count" => $exactCount,
                "density_percentage" => round($density, 2),
                "inflected_or_stem_matches_count" => $inflectedCount,
                "total_mentions_including_stems" => $totalMentions,
                "inflection_ratio" => round($inflectionRatio, 2),
                "ideal_density_range" => [1.0, 1.8],
                "deficit_to_reach_min_density" => (int)$deficit
            ];
        }

        // Secondary Keywords
        $secondaryMetrics = [];
        foreach ($this->secondaryKeywords as $sk) {
            $skWordCount = count(explode(' ', $sk));
            $skCount = mb_substr_count($cleanText, $sk);
            $skDensity = $wordCount > 0 ? ($skCount * $skWordCount / $wordCount) * 100 : 0;
            $status = $skDensity >= 0.4 ? "optimal" : "under_optimized";
            
            $secondaryMetrics[] = [
                "keyword" => $sk,
                "count" => $skCount,
                "density_percentage" => round($skDensity, 2),
                "status" => $status
            ];
        }

        // Over optimization / Stuffing
        $closestDistance = 9999;
        $stuffingScore = 0;
        
        if ($this->targetKeyword) {
            $kwParts = explode(' ', $this->targetKeyword);
            $positions = [];
            foreach ($words as $idx => $w) {
                if ($w === $kwParts[0]) {
                    $match = true;
                    for ($k = 1; $k < count($kwParts); $k++) {
                        if (!isset($words[$idx + $k]) || $words[$idx + $k] !== $kwParts[$k]) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) $positions[] = $idx;
                }
            }

            for ($i = 0; $i < count($positions) - 1; $i++) {
                $dist = $positions[$i+1] - $positions[$i];
                if ($dist < $closestDistance) $closestDistance = $dist;
                $stuffingScore += 100 / ($dist + 1);
            }
        }

        foreach ($topUnigrams as $ug) {
            if ($ug['density'] > 3.0) $stuffingScore += pow($ug['density'] - 2.5, 2);
        }
        foreach ($topBigrams as $bg) {
            if ($bg['density'] > 2.5) $stuffingScore += pow($bg['density'] - 2.5, 2);
        }

        $riskLevel = "LOW";
        if ($stuffingScore > 70) $riskLevel = "HIGH";
        elseif ($stuffingScore > 40) $riskLevel = "MEDIUM";

        return [
            "target_keyword_metrics" => empty($targetMetrics) ? null : $targetMetrics,
            "secondary_keywords_metrics" => $secondaryMetrics,
            "top_ngrams" => [
                "unigrams" => $topUnigrams,
                "bigrams" => $topBigrams,
                "trigrams" => $topTrigrams
            ],
            "over_optimization" => [
                "stuffing_penalty_score" => round($stuffingScore, 2),
                "risk_level" => $riskLevel,
                "closest_consecutive_keyword_distance_words" => $closestDistance === 9999 ? 0 : $closestDistance,
                "anomaly_flag" => $stuffingScore > 40
            ]
        ];
    }
}

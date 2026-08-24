<?php

namespace SeoEngine;

class ProminenceAnalyzer {
    private TextCleaner $cleaner;
    private ?string $targetKeyword;
    private array $secondaryKeywords;
    private array $headingsData;

    public function __construct(TextCleaner $cleaner, array $headingsData, ?string $targetKeyword = null, array $secondaryKeywords = []) {
        $this->cleaner = $cleaner;
        $this->headingsData = $headingsData;
        $this->targetKeyword = $targetKeyword ? mb_strtolower($targetKeyword, 'UTF-8') : null;
        $this->secondaryKeywords = array_map(fn($k) => mb_strtolower($k, 'UTF-8'), $secondaryKeywords);
    }

    public function analyze(): array {
        $words = $this->cleaner->getWords();
        $paragraphs = $this->cleaner->getParagraphs();
        $sentences = $this->cleaner->getSentences();

        $first100Words = array_slice($words, 0, 100);
        $first100Text = mb_strtolower(implode(' ', $first100Words), 'UTF-8');
        
        $containsTarget100 = false;
        $firstOccurenceIndex = -1;
        $isEarlyPositioned = false;

        if ($this->targetKeyword) {
            $containsTarget100 = mb_strpos($first100Text, $this->targetKeyword) !== false;
            if ($containsTarget100) {
                $kwParts = explode(' ', $this->targetKeyword);
                foreach ($first100Words as $idx => $w) {
                    if (mb_strtolower($w, 'UTF-8') === $kwParts[0]) {
                        $firstOccurenceIndex = $idx;
                        break;
                    }
                }
                $isEarlyPositioned = $firstOccurenceIndex > -1 && $firstOccurenceIndex <= 80;
            }
        }

        $lastParagraphText = '';
        if (!empty($paragraphs)) {
            $lastParagraph = end($paragraphs);
            $lastParagraphText = mb_strtolower($lastParagraph['clean'], 'UTF-8');
        }

        $lastParagraphContainsTarget = $this->targetKeyword && mb_strpos($lastParagraphText, $this->targetKeyword) !== false;
        $lastParagraphContainsSecondary = false;
        foreach ($this->secondaryKeywords as $sk) {
            if (mb_strpos($lastParagraphText, $sk) !== false) {
                $lastParagraphContainsSecondary = true;
                break;
            }
        }

        $lpStatus = "GAP_DETECTED";
        if ($lastParagraphContainsTarget || $lastParagraphContainsSecondary) {
            $lpStatus = "OPTIMAL";
        }

        $h1HasKeyword = false;
        $h1Index = -1;
        $h2Count = 0;
        $h2WithKeywordCount = 0;
        
        $structureTree = $this->headingsData['structure_tree'] ?? [];

        foreach ($structureTree as $h) {
            if ($h['level'] === 1) {
                if ($h['has_target_keyword']) {
                    $h1HasKeyword = true;
                    $kwParts = explode(' ', $this->targetKeyword);
                    $hWords = explode(' ', mb_strtolower($h['text'], 'UTF-8'));
                    foreach ($hWords as $idx => $w) {
                        if ($w === $kwParts[0]) {
                            $h1Index = $idx;
                            break;
                        }
                    }
                }
            } elseif ($h['level'] === 2) {
                $h2Count++;
                if ($h['has_target_keyword']) {
                    $h2WithKeywordCount++;
                } else {
                    $h2Lower = mb_strtolower($h['text'], 'UTF-8');
                    foreach ($this->secondaryKeywords as $sk) {
                        if (mb_strpos($h2Lower, $sk) !== false) {
                            $h2WithKeywordCount++;
                            break;
                        }
                    }
                }
            }
        }

        $h2Coverage = $h2Count > 0 ? ($h2WithKeywordCount / $h2Count) * 100 : 0;

        $frontLoadedOccurrences = 0;
        $totalOccurrences = 0;

        if ($this->targetKeyword) {
            foreach ($sentences as $sentence) {
                $sLower = mb_strtolower($sentence, 'UTF-8');
                $pos = mb_strpos($sLower, $this->targetKeyword);
                if ($pos !== false) {
                    $totalOccurrences++;
                    $sWords = explode(' ', $sLower);
                    $sLen = count($sWords);
                    $kwParts = explode(' ', $this->targetKeyword);
                    foreach ($sWords as $idx => $w) {
                        if (mb_strpos($w, $kwParts[0]) !== false) {
                            if ($idx / max(1, $sLen) <= 0.3) {
                                $frontLoadedOccurrences++;
                            }
                            break;
                        }
                    }
                }
            }
        }

        $frontLoadingRatio = $totalOccurrences > 0 ? ($frontLoadedOccurrences / $totalOccurrences) * 100 : 0;
        $flStatus = "NEEDS_IMPROVEMENT";
        if ($frontLoadingRatio >= 40) $flStatus = "EXCELLENT";

        return [
            "first_100_words" => [
                "contains_target_keyword" => $containsTarget100,
                "first_occurrence_word_index" => $firstOccurenceIndex,
                "is_early_positioned" => $isEarlyPositioned
            ],
            "last_paragraph" => [
                "contains_target_keyword" => $lastParagraphContainsTarget,
                "contains_secondary_keyword" => $lastParagraphContainsSecondary,
                "status" => $lpStatus
            ],
            "heading_prominence" => [
                "h1_has_keyword" => $h1HasKeyword,
                "h1_keyword_starts_at_index" => $h1Index,
                "h2_total_count" => $h2Count,
                "h2_with_keyword_or_lsi_count" => $h2WithKeywordCount,
                "h2_coverage_percentage" => round($h2Coverage, 2)
            ],
            "front_loading" => [
                "occurrences_in_first_30_percent_of_sentence" => $frontLoadedOccurrences,
                "total_occurrences" => $totalOccurrences,
                "front_loading_ratio_percentage" => round($frontLoadingRatio, 2),
                "status" => $flStatus
            ]
        ];
    }
}

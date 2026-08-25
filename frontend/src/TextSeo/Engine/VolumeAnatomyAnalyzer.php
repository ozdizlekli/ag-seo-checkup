<?php

namespace SeoEngine;

class VolumeAnatomyAnalyzer {
    private TextCleaner $cleaner;
    private ?string $targetKeyword;

    public function __construct(TextCleaner $cleaner, ?string $targetKeyword = null) {
        $this->cleaner = $cleaner;
        $this->targetKeyword = $targetKeyword ? TextCleaner::trToLower($targetKeyword) : null;
    }

    public function analyze(): array {
        $words = $this->cleaner->getWords();
        $sentences = $this->cleaner->getSentences();
        $paragraphs = $this->cleaner->getParagraphs();
        $cleanText = $this->cleaner->getCleanText();
        $rawText = $this->cleaner->getRawText();

        $wordCount = count($words);
        $charCountWithSpaces = mb_strlen($cleanText, 'UTF-8');
        $charCountNoSpaces = mb_strlen(preg_replace('/\s+/u', '', $cleanText), 'UTF-8');
        $syllableCount = $this->cleaner->getTotalSyllables();
        $sentenceCount = count($sentences);

        // Sentence Metrics
        $sentenceLengths = [];
        $sentencesOver25Words = 0;
        $sentencesOver25WordsIndexes = [];
        foreach ($sentences as $index => $sentence) {
            preg_match_all('/\p{L}+[\p{L}\p{Mn}\p{Pd}\'\’\p{N}]*/u', $sentence, $sWords);
            $count = count($sWords[0] ?? []);
            $sentenceLengths[] = $count;
            if ($count > 25) {
                $sentencesOver25Words++;
                $sentencesOver25WordsIndexes[] = $index;
            }
        }

        $meanSentenceLength = $sentenceCount > 0 ? array_sum($sentenceLengths) / $sentenceCount : 0;
        $variance = 0;
        foreach ($sentenceLengths as $len) {
            $variance += pow($len - $meanSentenceLength, 2);
        }
        $variance = $sentenceCount > 0 ? $variance / $sentenceCount : 0;
        $stdDev = sqrt($variance);
        
        $burstinessScore = 0;
        if ($stdDev + $meanSentenceLength > 0) {
            $burstinessScore = ($stdDev - $meanSentenceLength) / ($stdDev + $meanSentenceLength);
        }

        // Paragraph Metrics
        $totalParagraphWords = 0;
        $monolithicParagraphsCount = 0;
        $monolithicParagraphsIndexes = [];
        foreach ($paragraphs as $index => $p) {
            $totalParagraphWords += $p['word_count'];
            if ($p['word_count'] > 100 || $p['sentence_count'] > 6) {
                $monolithicParagraphsCount++;
                $monolithicParagraphsIndexes[] = $index;
            }
        }
        $pCount = count($paragraphs);
        $avgWordsPerParagraph = $pCount > 0 ? $totalParagraphWords / $pCount : 0;

        // Headings
        $headingsData = $this->analyzeHeadings($rawText);

        // Formatting
        $formattingData = $this->analyzeFormatting($rawText, $wordCount);

        return [
            "word_count" => $wordCount,
            "character_count" => [
                "with_spaces" => $charCountWithSpaces,
                "without_spaces" => $charCountNoSpaces
            ],
            "syllable_count" => $syllableCount,
            "sentence_count" => $sentenceCount,
            "paragraph_count" => $pCount,
            "sentence_metrics" => [
                "avg_sentence_length_words" => round($meanSentenceLength, 2),
                "standard_deviation" => round($stdDev, 2),
                "variance" => round($variance, 2),
                "burstiness_score" => round($burstinessScore, 2),
                "sentences_over_25_words" => $sentencesOver25Words,
                "sentences_over_25_words_indexes" => $sentencesOver25WordsIndexes,
                "monotonous_flow_detected" => $stdDev < 6.5
            ],
            "paragraph_metrics" => [
                "avg_words_per_paragraph" => round($avgWordsPerParagraph, 2),
                "monolithic_paragraphs_count" => $monolithicParagraphsCount,
                "monolithic_paragraphs_indexes" => $monolithicParagraphsIndexes,
                "thin_paragraphs_count" => 0
            ],
            "headings" => $headingsData,
            "formatting" => $formattingData
        ];
    }

    private function analyzeHeadings(string $rawText): array {
        $paragraphs = $this->cleaner->getParagraphs();
        $allHeadings = [];

        foreach ($paragraphs as $index => $p) {
            $cleanP = trim($p['clean']);
            $wordCount = $p['word_count'];
            
            if (empty($cleanP)) {
                continue;
            }

            $level = 0;
            
            $charCount = mb_strlen($cleanP, 'UTF-8');
            $isHeadingCandidate = false;

            if ($wordCount > 0 && $wordCount <= 12 && $charCount <= 80) {
                $lastChar = mb_substr($cleanP, -1, 1, 'UTF-8');
                
                preg_match_all('/\p{L}/u', $cleanP, $letters);
                $allLetters = implode('', $letters[0] ?? []);
                $isAllUpper = $allLetters !== '' && mb_strtoupper($allLetters, 'UTF-8') === $allLetters;

                if ($lastChar !== '.' && $lastChar !== ',') {
                    $isHeadingCandidate = true;
                } elseif ($lastChar === ':' || $lastChar === '?') {
                    $isHeadingCandidate = true;
                } elseif ($isAllUpper) {
                    $isHeadingCandidate = true;
                }
            }

            // H1 Rule: First paragraph and matches heading candidate
            if ($index === 0 && $isHeadingCandidate) {
                $level = 1;
            }
            // H2 Rule: Subsequent paragraph, matches candidate, single line
            elseif ($index > 0 && $isHeadingCandidate) {
                if (strpos(trim($p['raw']), "\n") === false) {
                    // Check if followed by an explanatory paragraph
                    if (isset($paragraphs[$index + 1])) {
                        $level = 2;
                    }
                }
            }

            if ($level > 0) {
                $allHeadings[] = [
                    'level' => $level,
                    'text' => $cleanP,
                    'p_index' => $index
                ];
            }
        }

        $counts = ['h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0];
        $structureTree = [];
        $hierarchyValid = true;
        $orphanHeadings = [];
        $lastLevel = 0;

        foreach ($allHeadings as $hIndex => $h) {
            $level = $h['level'];
            if ($level <= 6) {
                $counts["h{$level}"]++;
            }

            if ($lastLevel > 0 && $level > $lastLevel + 1) {
                $hierarchyValid = false;
                $orphanHeadings[] = $h['text'];
            }
            $lastLevel = $level;

            // Calculate words before next heading
            $wordsBeforeNext = 0;
            $nextPIndex = isset($allHeadings[$hIndex + 1]) ? $allHeadings[$hIndex + 1]['p_index'] : count($paragraphs);
            
            for ($i = $h['p_index'] + 1; $i < $nextPIndex; $i++) {
                $wordsBeforeNext += $paragraphs[$i]['word_count'];
            }

            $hasTarget = false;
            $earlyPositioned = false;
            if ($this->targetKeyword) {
                $hTextLower = TextCleaner::trToLower($h['text']);
                if (mb_strpos($hTextLower, $this->targetKeyword) !== false) {
                    $hasTarget = true;
                    $kwParts = explode(' ', $this->targetKeyword);
                    preg_match_all('/\p{L}+[\p{L}\p{Mn}\p{Pd}\'\’\p{N}]*/u', $h['text'], $hw);
                    $first3Words = implode(' ', array_slice($hw[0] ?? [], 0, 3));
                    if (mb_strpos(TextCleaner::trToLower($first3Words), $kwParts[0]) !== false) {
                        $earlyPositioned = true;
                    }
                }
            }

            $structureTree[] = [
                'level' => $level,
                'text' => $h['text'],
                'word_count_before_next' => $wordsBeforeNext,
                'has_target_keyword' => $hasTarget,
                'early_positioned' => $earlyPositioned
            ];
        }

        return [
            "h1_count" => $counts['h1'],
            "h2_count" => $counts['h2'],
            "h3_count" => $counts['h3'],
            "h4_count" => $counts['h4'],
            "structure_tree" => $structureTree,
            "hierarchy_valid" => $hierarchyValid,
            "orphan_headings" => $orphanHeadings
        ];
    }

    private function analyzeFormatting(string $rawText, int $wordCount): array {
        preg_match_all('/^(\*|-|\+|\d+\.)\s+/m', $rawText, $listMatches);
        $listItemsTotal = count($listMatches[0] ?? []);

        preg_match_all('/^(\d+\.)\s+/m', $rawText, $olMatches);
        $orderedListsCount = count($olMatches[0] ?? []);
        
        preg_match_all('/^(\*|-|\+)\s+/m', $rawText, $ulMatches);
        $unorderedListsCount = count($ulMatches[0] ?? []);

        preg_match_all('/\|.*\|/m', $rawText, $tableRows);
        $tablesCount = count(array_filter($tableRows[0] ?? [], function($row) {
            return strpos($row, '---') !== false;
        }));

        preg_match_all('/\*\*([^*]+)\*\*/u', $rawText, $boldMatches);
        $boldWordsCount = 0;
        foreach ($boldMatches[1] ?? [] as $b) {
            preg_match_all('/\p{L}+/u', $b, $w);
            $boldWordsCount += count($w[0] ?? []);
        }

        preg_match_all('/(?<!\*)\*([^*]+)\*(?!\*)/u', $rawText, $italicMatches);
        $italicWordsCount = 0;
        foreach ($italicMatches[1] ?? [] as $i) {
            preg_match_all('/\p{L}+/u', $i, $w);
            $italicWordsCount += count($w[0] ?? []);
        }

        $boldWordsRatio = $wordCount > 0 ? ($boldWordsCount / $wordCount) * 100 : 0;

        return [
            "list_items_total" => $listItemsTotal,
            "ordered_lists_count" => $orderedListsCount > 0 ? 1 : 0,
            "unordered_lists_count" => $unorderedListsCount > 0 ? 1 : 0,
            "tables_count" => $tablesCount > 0 ? 1 : 0, // Simplify count to block
            "bold_words_count" => $boldWordsCount,
            "bold_words_ratio_percentage" => round($boldWordsRatio, 2),
            "italic_words_count" => $italicWordsCount
        ];
    }
}

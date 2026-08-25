<?php

namespace SeoEngine;

class TextCleaner {
    private $rawText;
    private $cleanText;
    private $words = [];
    private $sentences = [];
    private $paragraphs = [];

    public function __construct(string $rawText) {
        $this->rawText = $rawText;
        $this->parse();
    }

    private function parse() {
        // Strip markdown and HTML tags for pure text analysis
        $this->cleanText = strip_tags($this->rawText);
        $this->cleanText = preg_replace('/[#*_`~>\[\]]/u', '', $this->cleanText);
        
        // Extract words (Unicode aware)
        preg_match_all('/\p{L}+[\p{L}\p{Mn}\p{Pd}\'\’\p{N}]*/u', $this->cleanText, $matches);
        $this->words = $matches[0] ?? [];

        // Mask abbreviations to prevent incorrect sentence splitting
        $abbreviations = ['dr', 'prof', 'av', 'vb', 'vs', 'sf', 'vd', 'doç', 'bkz', 'm\.ö', 'm\.s', 'yy', 'sn', 'dk', 'gr', 'kg'];
        $abbrevRegex = '/\b(' . implode('|', $abbreviations) . ')\.\s+/ui';
        
        $textForSplitting = preg_replace($abbrevRegex, '$1{{DOT}} ', $this->cleanText);

        // Extract sentences (allow uppercase, numbers, quotes, and parentheses as sentence starters)
        $splitRegex = '/(?<=[.!?…])\s+(?=[\p{Lu}\d"\'“”(«\[])/u';
        
        $rawSentences = preg_split($splitRegex, $textForSplitting, -1, PREG_SPLIT_NO_EMPTY);
        $this->sentences = [];
        foreach ($rawSentences as $s) {
            $this->sentences[] = str_replace('{{DOT}}', '.', trim($s));
        }

        if (empty($this->sentences) && !empty(trim($this->cleanText))) {
            $this->sentences = [$this->cleanText];
        }

        // Extract paragraphs with dynamic single-line heading detection
        $lines = preg_split('/\r\n|\r|\n/', trim($this->rawText));
        $blocks = [];
        $currentBlockLines = [];

        $flushBlock = function() use (&$blocks, &$currentBlockLines) {
            if (!empty($currentBlockLines)) {
                $blocks[] = implode("\n", $currentBlockLines);
                $currentBlockLines = [];
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $flushBlock();
                continue;
            }

            $cleanLine = trim(preg_replace('/[#*_`~>\[\]]/u', '', strip_tags($trimmed)));
            preg_match_all('/\p{L}+[\p{L}\p{Mn}\p{Pd}\'\’\p{N}]*/u', $cleanLine, $lineWords);
            $wordCount = count($lineWords[0] ?? []);
            $charCount = mb_strlen($cleanLine, 'UTF-8');

            $isHeadingCandidate = false;
            if ($wordCount > 0 && $wordCount <= 12 && $charCount <= 80) {
                $lastChar = mb_substr($cleanLine, -1, 1, 'UTF-8');
                
                preg_match_all('/\p{L}/u', $cleanLine, $letters);
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

            if ($isHeadingCandidate) {
                $flushBlock();
                $blocks[] = $line;
            } else {
                $currentBlockLines[] = $line;
            }
        }
        $flushBlock();

        foreach ($blocks as $p) {
            $cleanP = trim(preg_replace('/[#*_`~>\[\]]/u', '', strip_tags($p)));
            if (!empty($cleanP)) {
                preg_match_all('/\p{L}+[\p{L}\p{Mn}\p{Pd}\'\’\p{N}]*/u', $cleanP, $pWords);
                
                $pTextForSplitting = preg_replace($abbrevRegex, '$1{{DOT}} ', $cleanP);
                $pRawSentences = preg_split($splitRegex, $pTextForSplitting, -1, PREG_SPLIT_NO_EMPTY);
                $pSentences = [];
                foreach ($pRawSentences as $s) {
                    $pSentences[] = str_replace('{{DOT}}', '.', trim($s));
                }
                
                $this->paragraphs[] = [
                    'raw' => $p,
                    'clean' => $cleanP,
                    'word_count' => count($pWords[0] ?? []),
                    'sentence_count' => count($pSentences ?: [$cleanP])
                ];
            }
        }
    }

    public static function trToLower(string $text): string {
        return mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $text), 'UTF-8');
    }

    public function countSyllables(string $word): int {
        preg_match_all('/[aeıioöuüâîûAEIİOÖUÜÂÎÛ]/u', $word, $matches);
        $count = count($matches[0] ?? []);
        return $count > 0 ? $count : 1;
    }

    public function getTotalSyllables(): int {
        $total = 0;
        foreach ($this->words as $word) {
            $total += $this->countSyllables($word);
        }
        return $total;
    }

    public function getCleanText(): string { return $this->cleanText; }
    public function getRawText(): string { return $this->rawText; }
    public function getWords(): array { return $this->words; }
    public function getSentences(): array { return $this->sentences; }
    public function getParagraphs(): array { return $this->paragraphs; }
}

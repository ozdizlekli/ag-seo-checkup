<?php

namespace SeoEngine;

class LexicalSemanticsAnalyzer {
    private TextCleaner $cleaner;

    public function __construct(TextCleaner $cleaner) {
        $this->cleaner = $cleaner;
    }

    public function analyze(): array {
        $words = array_map(fn($w) => mb_strtolower($w, 'UTF-8'), $this->cleaner->getWords());
        $sentences = $this->cleaner->getSentences();
        
        $wordCount = count($words);
        $uniqueWords = array_unique($words);
        $uniqueWordCount = count($uniqueWords);
        
        $ttr = $wordCount > 0 ? $uniqueWordCount / $wordCount : 0;
        $guiraudR = $wordCount > 0 ? $uniqueWordCount / sqrt($wordCount) : 0;
        $statusLex = $ttr >= 0.4 ? "RICH" : "POOR";

        $wordFreq = array_count_values($words);
        
        $hapax = array_filter($wordFreq, fn($c) => $c === 1);
        $dis = array_filter($wordFreq, fn($c) => $c === 2);
        
        $hapaxCount = count($hapax);
        $disCount = count($dis);
        
        $hapaxRatio = $uniqueWordCount > 0 ? ($hapaxCount / $uniqueWordCount) * 100 : 0;
        $disRatio = $uniqueWordCount > 0 ? ($disCount / $uniqueWordCount) * 100 : 0;

        $stopwords = ["ve", "veya", "ama", "fakat", "lakin", "ancak", "için", "ile", "de", "da", "ki", "bir", "bu", "şu", "o", "mı", "mi", "mu", "mü", "çok", "daha", "en", "kadar", "gibi", "göre", "dolayı", "rağmen", "karşın", "yerine", "hakkında", "dair", "ait", "gibi", "ile", "için", "olan", "olarak"];
        
        $stopwordCount = 0;
        foreach ($words as $w) {
            if (in_array($w, $stopwords)) {
                $stopwordCount++;
            }
        }
        $stopwordRatio = $wordCount > 0 ? ($stopwordCount / $wordCount) * 100 : 0;
        $infoDensity = 100 - $stopwordRatio;
        
        $statusStop = "BALANCED";
        if ($stopwordRatio > 45) $statusStop = "HIGH_FLUFF";
        elseif ($stopwordRatio < 30) $statusStop = "LOW_STOPWORDS_KEYWORD_HEAVY";

        $qCount = 0;
        $qIndexes = [];
        $w1hPatternsMatches = [];
        $w1hRegex = '/\b(nedir|nasıl|neden|nerede|ne zaman|kim|kaç|hangisi)\b/ui';
        
        foreach ($sentences as $idx => $s) {
            if (mb_substr(trim($s), -1) === '?') {
                $qCount++;
                $qIndexes[] = $idx;
                preg_match_all($w1hRegex, $s, $m);
                if (!empty($m[0])) {
                    foreach ($m[0] as $match) {
                        $w1hPatternsMatches[] = mb_strtolower($match, 'UTF-8');
                    }
                }
            }
        }
        
        $w1hPatternsMatches = array_values(array_unique($w1hPatternsMatches));
        $sentenceCount = count($sentences);
        $qRatio = $sentenceCount > 0 ? ($qCount / $sentenceCount) * 100 : 0;
        
        return [
            "lexical_diversity" => [
                "unique_words_count" => $uniqueWordCount,
                "type_token_ratio" => round($ttr, 3),
                "guiraud_r_index" => round($guiraudR, 2),
                "status" => $statusLex
            ],
            "hapax_legomena" => [
                "single_occurrence_words_count" => $hapaxCount,
                "hapax_ratio_percentage" => round($hapaxRatio, 2),
                "dis_legomena_count" => $disCount,
                "dis_legomena_ratio_percentage" => round($disRatio, 2)
            ],
            "stopwords_and_density" => [
                "stopword_count" => $stopwordCount,
                "stopword_ratio_percentage" => round($stopwordRatio, 2),
                "information_density_percentage" => round($infoDensity, 2),
                "status" => $statusStop
            ],
            "questions_and_snippets" => [
                "question_sentences_count" => $qCount,
                "question_sentences_ratio_percentage" => round($qRatio, 2),
                "question_sentence_indexes" => $qIndexes,
                "5w1h_patterns_matched" => $w1hPatternsMatches,
                "snippet_candidate_found" => $qCount > 0
            ]
        ];
    }
}

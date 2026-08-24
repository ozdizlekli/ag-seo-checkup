<?php

namespace SeoEngine;

require_once __DIR__ . '/TextCleaner.php';
require_once __DIR__ . '/VolumeAnatomyAnalyzer.php';
require_once __DIR__ . '/ReadabilityAnalyzer.php';
require_once __DIR__ . '/KeywordFrequencyAnalyzer.php';
require_once __DIR__ . '/ProminenceAnalyzer.php';
require_once __DIR__ . '/LexicalSemanticsAnalyzer.php';
require_once __DIR__ . '/IntentEngagementAnalyzer.php';
require_once __DIR__ . '/TelemetryCompiler.php';

class SeoMetricEngine {
    /**
     * @param string $text
     * @param string|null $targetKeyword
     * @param array $secondaryKeywords
     * @return array
     */
    public static function analyze(string $text, ?string $targetKeyword = null, array $secondaryKeywords = []): array {
        $startTime = microtime(true);

        $cleaner = new TextCleaner($text);
        
        $anatomyAnalyzer = new VolumeAnatomyAnalyzer($cleaner, $targetKeyword);
        $anatomyData = $anatomyAnalyzer->analyze();

        $readabilityAnalyzer = new ReadabilityAnalyzer($cleaner);
        $readabilityData = $readabilityAnalyzer->analyze();

        $keywordAnalyzer = new KeywordFrequencyAnalyzer($cleaner, $targetKeyword, $secondaryKeywords);
        $keywordData = $keywordAnalyzer->analyze();

        $prominenceAnalyzer = new ProminenceAnalyzer($cleaner, $anatomyData['headings'], $targetKeyword, $secondaryKeywords);
        $prominenceData = $prominenceAnalyzer->analyze();

        $lexicalAnalyzer = new LexicalSemanticsAnalyzer($cleaner);
        $lexicalData = $lexicalAnalyzer->analyze();

        $intentAnalyzer = new IntentEngagementAnalyzer($cleaner);
        $intentData = $intentAnalyzer->analyze();

        $executionTimeMs = (microtime(true) - $startTime) * 1000;

        $compiler = new TelemetryCompiler();
        return $compiler->compile(
            $anatomyData,
            $readabilityData,
            $keywordData,
            $prominenceData,
            $lexicalData,
            $intentData,
            $executionTimeMs,
            $targetKeyword,
            $secondaryKeywords
        );
    }
}

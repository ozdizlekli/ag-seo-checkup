<?php

namespace SeoEngine;

class TelemetryCompiler {
    public function compile(
        array $anatomy,
        array $readability,
        array $keywords,
        array $prominence,
        array $lexical,
        array $intent,
        float $executionTime,
        ?string $targetKeyword,
        array $secondaryKeywords
    ): array {
        return [
            '$schema' => "https://json-schema.org/draft/2020-12/schema",
            'meta' => [
                'engine_version' => "PHP-SEO-NLP-v2.4",
                'execution_time_ms' => round($executionTime, 2),
                'timestamp' => gmdate("Y-m-d\TH:i:s\Z"),
                'language' => "tr-TR",
                'target_keyword' => $targetKeyword,
                'secondary_keywords' => $secondaryKeywords
            ],
            'telemetry_data' => [
                'anatomy' => $anatomy,
                'readability' => $readability,
                'keywords_and_frequency' => $keywords,
                'prominence_and_positioning' => $prominence,
                'lexical_and_semantics' => $lexical,
                'intent_and_action' => $intent
            ]
        ];
    }
}

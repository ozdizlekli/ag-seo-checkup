<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Only POST method is allowed"]);
    exit;
}

require_once __DIR__ . '/../Engine/TextCleaner.php';
require_once __DIR__ . '/../Engine/VolumeAnatomyAnalyzer.php';
require_once __DIR__ . '/../Engine/ReadabilityAnalyzer.php';
require_once __DIR__ . '/../Engine/ProminenceAnalyzer.php';
require_once __DIR__ . '/../Engine/KeywordFrequencyAnalyzer.php';
require_once __DIR__ . '/../Engine/LexicalSemanticsAnalyzer.php';
require_once __DIR__ . '/../Engine/IntentEngagementAnalyzer.php';
require_once __DIR__ . '/../Engine/TelemetryCompiler.php';
require_once __DIR__ . '/../Engine/SeoMetricEngine.php';
require_once __DIR__ . '/../Services/GeminiService.php';

use SeoEngine\SeoMetricEngine;
use Services\GeminiService;

try {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!$input || empty($input['text'])) {
        throw new \Exception("JSON payload içinde 'text' (metin) parametresi zorunludur.");
    }

    $rawText = $input['text'];
    error_log("[CMD LOG] >>> Yeni analiz isteği alındı. Metin uzunluğu: " . mb_strlen($rawText) . " karakter.");
    $targetKeyword = $input['target_keyword'] ?? null;
    $secondaryKeywords = $input['secondary_keywords'] ?? [];

    $geminiService = new GeminiService();

    $stageAExecuted = false;

    // Aşama A (Semantik Keşif): Kullanıcı hedef anahtar kelime belirtmediyse
    if (empty($targetKeyword)) {
        error_log("[CMD LOG] [Aşama A] Hedef kelime girilmedi, Gemini ile semantik keşif başlatılıyor...");
        $discoveryResult = $geminiService->discoverSemantics($rawText);
        $targetKeyword = $discoveryResult['target_keyword'] ?? 'Bilinmeyen Odak Kelime';
        
        if (empty($secondaryKeywords) && !empty($discoveryResult['secondary_keywords'])) {
            $secondaryKeywords = $discoveryResult['secondary_keywords'];
        }
        $stageAExecuted = true;
        error_log("[CMD LOG] [Aşama A] Keşif tamamlandı. Odak Kelime: " . $targetKeyword);
    }

    // PHP Telemetry Json Verisi Üretimi
    error_log("[CMD LOG] [PHP Motoru] Deterministik SEO metrikleri hesaplanıyor...");
    $telemetryData = SeoMetricEngine::analyze($rawText, $targetKeyword, $secondaryKeywords);
    error_log("[CMD LOG] [PHP Motoru] Hesaplama tamamlandı (" . round($telemetryData['meta']['execution_time_ms'], 2) . " ms).");

    // Aşama B (4 Temel Boyut): Uzman sistem promptları çalıştırılarak sonuç alınması
    error_log("[CMD LOG] [Aşama B] Gemini 4 Boyutlu analiz çağrısı gönderiliyor...");
    $expertInsights = $geminiService->generateExpertInsights($telemetryData, $rawText);
    error_log("[CMD LOG] [Aşama B] Gemini yanıtı başarıyla alındı ve JSON parse edildi.");

    // --- GÜVENCE MEKANİZMASI: skor_dagilimi NORMALİZASYONU ---
    if (!isset($expertInsights['analiz'])) {
        $expertInsights['analiz'] = [];
    }
    
    $genelSkor = isset($expertInsights['analiz']['saglik_skoru']) ? (int)$expertInsights['analiz']['saglik_skoru'] : 75;
    $expertInsights['analiz']['saglik_skoru'] = $genelSkor;
    
    // AI farklı yere koymuşsa bulup al
    $rawSkorDagilimi = [];
    if (isset($expertInsights['analiz']['skor_dagilimi']) && is_array($expertInsights['analiz']['skor_dagilimi'])) {
        $rawSkorDagilimi = $expertInsights['analiz']['skor_dagilimi'];
    } elseif (isset($expertInsights['skor_dagilimi']) && is_array($expertInsights['skor_dagilimi'])) {
        $rawSkorDagilimi = $expertInsights['skor_dagilimi'];
        unset($expertInsights['skor_dagilimi']);
    }
    
    // Anahtarları normalize et ve string değerleri integer'a çevir
    $normalizedSkorDagilimi = [];
    foreach ($rawSkorDagilimi as $k => $v) {
        $kLower = strtolower($k);
        if (strpos($kLower, 'anahtar') !== false || strpos($kLower, 'uyum') !== false) {
            $normalizedSkorDagilimi['anahtar_kelime_uyumu'] = (int)$v;
        } elseif (strpos($kLower, 'okuna') !== false || strpos($kLower, 'akici') !== false) {
            $normalizedSkorDagilimi['okunabilirlik'] = (int)$v;
        } elseif (strpos($kLower, 'yapi') !== false || strpos($kLower, 'icerik') !== false) {
            $normalizedSkorDagilimi['icerik_yapisi'] = (int)$v;
        } elseif (strpos($kLower, 'bilgi') !== false || strpos($kLower, 'yogun') !== false) {
            $normalizedSkorDagilimi['bilgi_yogunlugu'] = (int)$v;
        } elseif (strpos($kLower, 'ikna') !== false || strpos($kLower, 'uzman') !== false) {
            $normalizedSkorDagilimi['ikna_edicilik'] = (int)$v;
        }
    }
    
    $requiredKeys = [
        'anahtar_kelime_uyumu' => 25,
        'okunabilirlik' => 25,
        'icerik_yapisi' => 20,
        'bilgi_yogunlugu' => 15,
        'ikna_edicilik' => 15
    ];
    
    $hasAllKeys = true;
    $toplamSkor = 0;
    foreach (array_keys($requiredKeys) as $rk) {
        if (!isset($normalizedSkorDagilimi[$rk])) {
            $hasAllKeys = false;
            break;
        }
        $toplamSkor += $normalizedSkorDagilimi[$rk];
    }
    
    // Eğer tüm anahtarlar yoksa veya toplam skor genelSkor ile tutarsızsa (tolerans ±2)
    if (!$hasAllKeys || abs($toplamSkor - $genelSkor) > 2) {
        $normalizedSkorDagilimi = [];
        $assignedTotal = 0;
        $keys = array_keys($requiredKeys);
        
        for ($i = 0; $i < count($keys); $i++) {
            $rk = $keys[$i];
            $agirlik = $requiredKeys[$rk];
            
            if ($i === count($keys) - 1) {
                // Son kritere kalanı ata ki toplam %100 tutsun
                $normalizedSkorDagilimi[$rk] = max(0, $genelSkor - $assignedTotal);
            } else {
                $puan = (int)round(($agirlik / 100) * $genelSkor);
                $normalizedSkorDagilimi[$rk] = $puan;
                $assignedTotal += $puan;
            }
        }
    }
    
    // Garanti altına alınmış veriyi yerine koy
    $expertInsights['analiz']['skor_dagilimi'] = $normalizedSkorDagilimi;
    // ---------------------------------------------------------

    // Frontend için zenginleştirilmiş JSON yanıtı
    $response = [
        'success' => true,
        'orchestration' => [
            'stage_a_executed' => $stageAExecuted,
            'target_keyword' => $targetKeyword,
            'secondary_keywords' => $secondaryKeywords
        ],
        'telemetry_summary' => $telemetryData,
        'ai_dimensions' => $expertInsights
    ];

    error_log("[CMD LOG] <<< İstek başarıyla tamamlandı, JSON tarayıcıya iletiliyor.\n");
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    error_log("[CMD HATA] !!! İstek sırasında hata oluştu: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

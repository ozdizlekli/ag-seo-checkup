<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../src/TextSeo/Services/GeminiService.php';

use Services\GeminiService;

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Sadece POST desteklenir.']); exit; }

$now = time();
$key = 'technical_ai_explain';
$_SESSION[$key] ??= ['count' => 0, 'window_start' => $now, 'cache' => []];
if ($now - (int) $_SESSION[$key]['window_start'] > 60) $_SESSION[$key] = ['count' => 0, 'window_start' => $now, 'cache' => $_SESSION[$key]['cache'] ?? []];
if (++$_SESSION[$key]['count'] > 10) { http_response_code(429); echo json_encode(['error' => 'Çok fazla AI isteği. Lütfen bir dakika bekleyin.']); exit; }

$payload = json_decode((string) file_get_contents('php://input'), true);
$finding = is_array($payload['finding'] ?? null) ? $payload['finding'] : null;
if ($finding === null) { http_response_code(400); echo json_encode(['error' => 'Geçerli bulgu gönderilmedi.']); exit; }
$cacheKey = hash('sha256', json_encode($finding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if (isset($_SESSION[$key]['cache'][$cacheKey])) { echo json_encode(['data' => $_SESSION[$key]['cache'][$cacheKey], 'cached' => true]); exit; }

try {
    set_time_limit(45);
    $data = (new GeminiService())->explainTechnicalFinding($finding);
    $_SESSION[$key]['cache'][$cacheKey] = $data;
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[Technical AI] Açıklama üretilemedi: ' . get_class($e));
    http_response_code(503);
    echo json_encode(['error' => 'AI destekli açıklama şu anda kullanılamıyor. Deterministik çözüm adımları kullanılmaya devam edebilir.'], JSON_UNESCAPED_UNICODE);
}

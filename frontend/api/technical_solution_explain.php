<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();
header('Content-Type: application/json; charset=utf-8');

$responseSent = false;
function sendJson(array $body, int $status = 200): never
{
    global $responseSent;
    $responseSent = true;
    if (ob_get_length() !== false) ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json !== false ? $json : '{"success":false,"error":"AI çözümü üretilemedi.","code":"JSON_ENCODING_ERROR"}';
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    error_log('[Technical AI] PHP uyarısı: ' . $message . ' in ' . basename($file) . ':' . $line);
    return true;
});
register_shutdown_function(static function () use (&$responseSent): void {
    if ($responseSent) return;
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[Technical AI] Fatal PHP hatası: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']);
        if (ob_get_length() !== false) ob_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo '{"success":false,"error":"AI çözümü üretilemedi.","code":"INTERNAL_ERROR"}';
    }
});

require_once __DIR__ . '/../auth.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    sendJson(['success' => false, 'error' => 'Bu işlem için giriş yapmanız gerekiyor.', 'code' => 'UNAUTHORIZED'], 401);
}
require __DIR__ . '/../src/TechnicalSeo/Bootstrap.php';

use App\TechnicalSeo\GeminiSolutionException;
use App\TechnicalSeo\GeminiSolutionExplainer;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['success' => false, 'error' => 'Sadece POST desteklenir.', 'code' => 'METHOD_NOT_ALLOWED'], 405);
$now = time();
$key = 'technical_ai_explain';
$_SESSION[$key] ??= ['count' => 0, 'window_start' => $now, 'cache' => []];
if ($now - (int) $_SESSION[$key]['window_start'] > 60) $_SESSION[$key] = ['count' => 0, 'window_start' => $now, 'cache' => $_SESSION[$key]['cache'] ?? []];
if (++$_SESSION[$key]['count'] > 10) sendJson(['success' => false, 'error' => 'Çok fazla AI isteği. Lütfen bir dakika bekleyin.', 'code' => 'RATE_LIMITED'], 429);

try {
    $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    sendJson(['success' => false, 'error' => 'Geçerli JSON gönderilmedi.', 'code' => 'INVALID_JSON'], 400);
}
$finding = is_array($payload['finding'] ?? null) ? $payload['finding'] : null;
if ($finding === null) sendJson(['success' => false, 'error' => 'Geçerli bulgu gönderilmedi.', 'code' => 'INVALID_FINDING'], 400);
// Kullanici arayuzdeki yenile ikonuyla acikca "tekrar uret" isterse (force:true),
// eski session cache'ini OKUMADAN atliyoruz - ama yine de sonucu ayni key'e
// yazip guncelliyoruz, boylece sonraki normal isteklerde bu yeni cevap donuyor.
$forceRefresh = ($payload['force'] ?? false) === true;
$cacheKey = hash('sha256', json_encode($finding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if (!$forceRefresh && isset($_SESSION[$key]['cache'][$cacheKey])) sendJson(['success' => true, 'solution' => $_SESSION[$key]['cache'][$cacheKey], 'cached' => true]);

try {
    set_time_limit(45);
    $solution = (new GeminiSolutionExplainer())->explain($finding);
    $_SESSION[$key]['cache'][$cacheKey] = $solution;
    sendJson(['success' => true, 'solution' => $solution]);
} catch (GeminiSolutionException $e) {
    error_log('[Technical AI] Çözüm üretilemedi; code=' . $e->responseCode . ', detail=' . $e->getMessage());
    sendJson(['success' => false, 'error' => 'AI çözümü üretilemedi.', 'code' => $e->responseCode], $e->httpStatus);
} catch (Throwable $e) {
    error_log('[Technical AI] Beklenmeyen hata: ' . get_class($e) . ' - ' . $e->getMessage());
    sendJson(['success' => false, 'error' => 'AI çözümü üretilemedi.', 'code' => 'INTERNAL_ERROR'], 500);
}

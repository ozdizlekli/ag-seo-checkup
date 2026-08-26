<?php
/**
 * form_submit.php — Gemini API Proxy
 * 
 * GÜVENLİK:
 * - Oturum açmış kullanıcılar ile sınırlı
 * - Rate limiting: kullanıcı başına dakikada maks 20 istek
 */

require_once __DIR__ . '/auth.php';
require_login();

// .env dosyasını oku
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($env) {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
    }
}

header('Content-Type: application/json');
set_time_limit(120);
ini_set('max_execution_time', '120');

// --- Rate Limiting (session tabanlı, dakikada 20 istek) ---
$rateLimitKey = 'form_submit_reqs';
$rateLimitWindow = 60;    // saniye
$rateLimitMax = 20;       // max istek

$now = time();
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'window_start' => $now];
}
$rl = &$_SESSION[$rateLimitKey];
if (($now - $rl['window_start']) > $rateLimitWindow) {
    // Yeni pencere
    $rl = ['count' => 0, 'window_start' => $now];
}
$rl['count']++;
if ($rl['count'] > $rateLimitMax) {
    http_response_code(429);
    echo json_encode(['error' => 'Çok fazla istek. Lütfen bir dakika bekleyin.']);
    exit;
}

// Gelen isteği al
$inputJSON = file_get_contents('php://input');
if (!$inputJSON) {
    http_response_code(400);
    echo json_encode(['error' => 'No input provided']);
    exit;
}

// Temel JSON geçerlilik kontrolü
$decoded = json_decode($inputJSON, true);
if ($decoded === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

if (empty($apiKey) || $apiKey === 'BURAYA_API_KEY_GELECEK') {
    http_response_code(500);
    echo json_encode(['error' => 'API Key is missing or invalid in .env file']);
    exit;
}

// Gemini API'ye istek at
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  // SSL doğrulaması aktif
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $errMsg = curl_error($ch);
    curl_close($ch);
    error_log("form_submit.php cURL hatası: " . $errMsg);
    http_response_code(502);
    echo json_encode(['error' => 'API isteği başarısız oldu.']);
    exit;
}

curl_close($ch);
http_response_code($httpCode);
echo $response;

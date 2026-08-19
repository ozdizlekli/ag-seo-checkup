<?php
// .env dosyasını oku
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

header('Content-Type: application/json');

// Gelen isteği al
$inputJSON = file_get_contents('php://input');
if (!$inputJSON) {
    echo json_encode(['error' => 'No input provided']);
    exit;
}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

if (empty($apiKey) || $apiKey === 'BURAYA_API_KEY_GELECEK') {
    echo json_encode(['error' => 'API Key is missing or invalid in .env file']);
    exit;
}

// Gemini API'ye istek at
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Local sunucular için (gerekirse)

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if(curl_errno($ch)){
    echo json_encode(['error' => curl_error($ch)]);
} else {
    http_response_code($httpCode);
    echo $response;
}

curl_close($ch);
?>

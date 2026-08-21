<?php
$envPath = __DIR__ . '/.env';
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $_ENV[trim($name)] = trim($value);
}
$apiKey = $_ENV['GEMINI_API_KEY'];
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
echo "URL: " . $url . "\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"contents":[{"parts":[{"text":"hello"}]}]}');
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP: " . $httpCode . "\n";
echo "Response: " . $response . "\n";
?>

<?php
/**
 * SEO Metin Optimizasyonu — Proje Konfigürasyonu
 * Tüm sabitler bu dosyada tanımlanır.
 */

// Basit .env Okuyucu (Harici kütüphane kullanmadan)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Yorum satırlarını atla
        if (strpos($line, '#') === 0 || $line === '') {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Çift ve tek tırnakları temizle
            $value = trim($value, '"\'');
            
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Ortam değişkeninden okuma yardımcı fonksiyonu
function get_env_value($key, $default = '') {
    $val = getenv($key);
    if ($val !== false) return $val;
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    return $default;
}

// Gemini API Ayarları
define('GEMINI_API_KEY', get_env_value('GEMINI_API_KEY', 'FALLBACK_API_KEY'));
define('GEMINI_MODEL', get_env_value('GEMINI_MODEL', 'gemini-3.6-flash'));
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/');

// SEO Karakter Limitleri
define('SEO_TITLE_MIN', 50);
define('SEO_TITLE_MAX', 60);
define('SEO_DESC_MIN', 150);
define('SEO_DESC_MAX', 160);

// Metin Koruma Kuralları
define('TEXT_PRESERVE_RATIO', 0.90);   // Metnin en az %90'ı korunmalı
define('WORD_COUNT_TOLERANCE', 0.10);  // Kelime sayısı ±%10 tolerans

// Teknik Ayarlar
define('MAX_SCRAPE_TIMEOUT', 15);      // Sayfa çekme timeout (saniye)
define('GEMINI_TIMEOUT', 45);         // Maksimum 45 saniye
define('LOG_DIR', __DIR__ . '/logs/'); // Log dosyaları dizini

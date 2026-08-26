<?php
/**
 * db.php — Veritabanı bağlantısı
 *
 * .env dosyasını $_ENV'e yükler, ardından bağlantıyı kurar.
 * $pdo: başarılıysa PDO nesnesi, başarısızsa null (uygulamada JSON fallback devreye girer).
 * Hata ayrıntısı kullanıcıya gösterilmez — yalnızca error_log'a yazılır.
 */

// .env'i $_ENV'e yükle (henüz yüklenmemişse)
// Not: form_submit.php ve GeminiService.php kendi .env okuma bloklarına sahip;
// bu merkezi yükleme diğer tüm dosyaların (login.php, api/*.php vb.) doğru
// DB kimlik bilgilerini almasını sağlar.
if (empty($_ENV['_AGSEO_ENV_LOADED'])) {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if (is_array($env)) {
            foreach ($env as $key => $value) {
                // Zaten tanımlıysa (örn. Docker ortam değişkeni) üzerine yazma
                if (!isset($_ENV[$key])) {
                    $_ENV[$key]  = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }
    $_ENV['_AGSEO_ENV_LOADED'] = '1';
}

$host   = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'ag_seo_db';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';
$pdo    = null;

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Hata kullanıcıya gösterilmez — sunucu loguna yazılır
    error_log("db.php: Veritabanı bağlantısı kurulamadı — " . $e->getMessage());
    $pdo = null;
    // Demo modunda uygulama çalışmaya devam eder (JSON fallback)
}

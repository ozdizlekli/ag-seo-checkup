<?php
/**
 * db.php — Veritabanı bağlantısı
 * 
 * $pdo değişkeni başarılı bağlantıda PDO nesnesi, başarısızda null olur.
 * Hata durumu sessizce yutulmaz — error_log'a yazılır.
 */

$host   = $_ENV['DB_HOST']   ?? getenv('DB_HOST')   ?: 'localhost';
$dbname = $_ENV['DB_NAME']   ?? getenv('DB_NAME')   ?: 'ag_seo_db';
$user   = $_ENV['DB_USER']   ?? getenv('DB_USER')   ?: 'root';
$pass   = $_ENV['DB_PASS']   ?? getenv('DB_PASS')   ?: '';
$pdo    = null;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
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

<?php
/**
 * SEO Metin Optimizasyonu — Geçmiş API'si
 * 
 * GET /api/history.php : Tüm geçmişi listele
 * GET /api/history.php?id=X : ID'ye göre detay getir
 * POST /api/history.php (action=delete, id=X) : ID'ye göre sil
 * POST /api/history.php (action=clear) : Tüm geçmişi temizle
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../includes/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_ENV['ALLOWED_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

AuthMiddleware::verify();

if (!$pdo) {
    echo json_encode(['status' => 'error', 'error' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

// Tablo yoksa oluştur
$pdo->exec("
CREATE TABLE IF NOT EXISTS text_seo_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    analysis_number INT DEFAULT 1,
    is_reanalysis TINYINT(1) DEFAULT 0,
    used_keywords LONGTEXT,
    original_data LONGTEXT,
    optimized_data LONGTEXT,
    analysis_data LONGTEXT,
    keywords_data LONGTEXT,
    debug_trace LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id > 0) {
        // Tekil detay
        $stmt = $pdo->prepare("SELECT * FROM text_seo_history WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        
        if ($row) {
            $responsePayload = [
                'id'              => $row['id'],
                'url'             => $row['url'],
                'analysis_number' => $row['analysis_number'],
                'is_reanalysis'   => (bool)$row['is_reanalysis'],
                'original'        => json_decode($row['original_data'], true),
                'optimized'       => json_decode($row['optimized_data'], true),
                'analysis'        => json_decode($row['analysis_data'], true),
                'keywords'        => json_decode($row['keywords_data'], true),
                'debug_trace'     => json_decode($row['debug_trace'], true),
                'created_at'      => $row['created_at']
            ];
            
            echo json_encode(['status' => 'success', 'data' => $responsePayload], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'Kayıt bulunamadı.'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Liste (sadece temel bilgiler)
        $stmt = $pdo->query("SELECT id, url, analysis_number, is_reanalysis, original_data, keywords_data, created_at FROM text_seo_history ORDER BY id DESC");
        $results = $stmt->fetchAll();
        
        $list = [];
        foreach ($results as $row) {
            $kwData = json_decode($row['keywords_data'], true);
            $focus = $kwData['focus'] ?? '';
            
            $orig = json_decode($row['original_data'], true);
            $title = $orig['title'] ?? '';
            
            $list[] = [
                'id' => $row['id'],
                'url' => $row['url'],
                'title' => $title,
                'analysis_number' => $row['analysis_number'],
                'is_reanalysis' => $row['is_reanalysis'],
                'focus_keyword' => $focus,
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode(['status' => 'success', 'data' => $list], JSON_UNESCAPED_UNICODE);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    if ($action === 'delete') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM text_seo_history WHERE id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['status' => 'success', 'message' => 'Kayıt silindi.'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Geçersiz ID.'], JSON_UNESCAPED_UNICODE);
        }
    } elseif ($action === 'clear' || $action === 'clear_all') {
        $pdo->exec("TRUNCATE TABLE text_seo_history");
        echo json_encode(['status' => 'success', 'message' => 'Tüm geçmiş temizlendi.'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Geçersiz action.'], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'Geçersiz metod.'], JSON_UNESCAPED_UNICODE);
}

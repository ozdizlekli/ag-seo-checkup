<?php
/**
 * Merkezi Authentication Middleware
 * 
 * Tüm korumalı endpoint'lerin başına require_once ile dahil edilmeli.
 * Session başlatır ve giriş kontrolü yapar.
 * Yetkisiz erişimlerde 401 döner ve durur.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        http_response_code(401);
        // Eğer JSON beklenen bir endpoint ise JSON hata dön
        $acceptsJson = (
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        );
        if ($acceptsJson) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized', 'message' => 'Bu işlem için giriş yapmanız gerekiyor.']);
        } else {
            header('Location: /login.php');
        }
        exit;
    }
}

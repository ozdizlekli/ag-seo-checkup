<?php
$host = 'localhost';
$dbname = 'ag_seo_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // MySQL connection error can be handled here.
    // For now we just silently fail or log, so the frontend works even without DB for demo.
}
?>

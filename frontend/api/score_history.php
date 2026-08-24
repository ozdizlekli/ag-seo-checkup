<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['error' => 'No database connection']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
    if (isset($_GET['client_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM score_history WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_GET['client_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $rows]);
    } else {
        echo json_encode(['error' => 'Missing client_id']);
    }
} elseif ($method === 'POST') {
    // Array of objects like supabase
    if (is_array($data) && isset($data[0])) {
        $row = $data[0];
        $stmt = $pdo->prepare("INSERT INTO score_history (client_id, content_score, keyword_score, technical_score, schema_score, offsite_score, overall_score) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $row['client_id'] ?? null,
            $row['content_score'] ?? 0,
            $row['keyword_score'] ?? 0,
            $row['technical_score'] ?? 0,
            $row['schema_score'] ?? 0,
            $row['offsite_score'] ?? 0,
            $row['overall_score'] ?? 0
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid data format']);
    }
}
?>

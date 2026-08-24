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
        $stmt = $pdo->prepare("SELECT * FROM content_history WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_GET['client_id']]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $history]);
    } else {
        echo json_encode(['error' => 'Missing client_id']);
    }
} elseif ($method === 'POST') {
    if (isset($data['client_id'])) {
        $stmt = $pdo->prepare("INSERT INTO content_history (client_id, keyword, old_text, new_text, project_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['client_id'],
            $data['keyword'] ?? null,
            $data['old_text'] ?? null,
            $data['new_text'] ?? null,
            $data['project_type'] ?? null
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Missing data']);
    }
}
?>

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
        $stmt = $pdo->prepare("SELECT * FROM client_keywords WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_GET['client_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $rows]);
    } else {
        echo json_encode(['error' => 'Missing client_id']);
    }
} elseif ($method === 'POST') {
    // Expected array of objects
    if (is_array($data)) {
        $stmt = $pdo->prepare("INSERT INTO client_keywords (client_id, keyword, opportunity_score, volume, difficulty, cpc, intent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($data as $row) {
            $stmt->execute([
                $row['client_id'] ?? null,
                $row['keyword'] ?? null,
                $row['opportunity_score'] ?? 0,
                $row['volume'] ?? 0,
                $row['difficulty'] ?? 0,
                $row['cpc'] ?? 0.00,
                $row['intent'] ?? null
            ]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Missing data array']);
    }
}
?>

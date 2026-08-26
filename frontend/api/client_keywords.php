<?php
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['error' => 'No database connection']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
    if (isset($_GET['client_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM client_keywords WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([(int)$_GET['client_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $rows]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing client_id']);
    }
} elseif ($method === 'POST') {
    // Expected array of objects
    if (is_array($data)) {
        $stmt = $pdo->prepare("INSERT INTO client_keywords (client_id, keyword, opportunity_score, volume, difficulty, cpc, intent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($data as $row) {
            $stmt->execute([
                isset($row['client_id']) ? (int)$row['client_id'] : null,
                $row['keyword'] ?? null,
                (int)($row['opportunity_score'] ?? 0),
                (int)($row['volume'] ?? 0),
                (int)($row['difficulty'] ?? 0),
                (float)($row['cpc'] ?? 0.00),
                $row['intent'] ?? null
            ]);
        }
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing data array']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

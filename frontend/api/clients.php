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
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $clients]);
} elseif ($method === 'POST') {
    if (isset($data['name'])) {
        $stmt = $pdo->prepare("INSERT INTO clients (name, domain_url, drive_folder_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['domain_url'] ?? null,
            $data['drive_folder_id'] ?? null
        ]);
        $id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['data' => [$client]]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing name']);
    }
} elseif ($method === 'PUT') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $updates = [];
        $params = [];
        foreach (['name', 'domain_url', 'drive_folder_id'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (!empty($updates)) {
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE clients SET " . implode(", ", $updates) . " WHERE id = ?");
            $stmt->execute($params);
        }
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
    }
} elseif ($method === 'DELETE') {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

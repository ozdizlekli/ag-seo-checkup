<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? 'anonymous';
$history_file = 'chat_history.json';

// Load history from file
$all_history = [];
if (file_exists($history_file)) {
    $all_history = json_decode(file_get_contents($history_file), true) ?: [];
}

if (!isset($all_history[$username])) {
    $all_history[$username] = [];
}

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($data && isset($data['url'])) {
        $chatId = $data['chatId'] ?? time();
        
        $all_history[$username][$chatId] = [
            'chatId' => $chatId,
            'url' => $data['url'],
            'type' => $data['type'],
            'messages' => $data['messages'] ?? [],
            'completedSteps' => $data['completedSteps'] ?? [],
            'reportData' => $data['reportData'] ?? [],
            'fixedIssues' => $data['fixedIssues'] ?? [],
            'date' => date('d.m.Y H:i')
        ];
        
        file_put_contents($history_file, json_encode($all_history));
        echo json_encode(['success' => true, 'chatId' => $chatId]);
    } else {
        echo json_encode(['error' => 'Geçersiz veri']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return history list
    $history = array_values($all_history[$username]);
    // Sort by newest first
    usort($history, function($a, $b) {
        return $b['chatId'] <=> $a['chatId'];
    });
    echo json_encode(['history' => $history]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (isset($_GET['id']) && $_GET['id'] !== 'all') {
        unset($all_history[$username][$_GET['id']]);
        file_put_contents($history_file, json_encode($all_history));
        echo json_encode(['success' => true]);
    } else {
        $all_history[$username] = [];
        file_put_contents($history_file, json_encode($all_history));
        echo json_encode(['success' => true]);
    }
}

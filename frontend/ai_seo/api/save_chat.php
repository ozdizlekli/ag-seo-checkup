<?php
/**
 * save_chat.php — Chat geçmişi kayıt/okuma API'si
 * 
 * Veritabanı (MySQL) ve JSON dosya yedeklemesini hibrit olarak destekler.
 * Oturum açmış kullanıcının geçmişini ve daha önce 'anonymous' olarak
 * kaydedilmiş sohbetleri otomatik birleştirerek hiçbir sohbetin kaybolmamasını sağlar.
 */

error_reporting(0);
ini_set("display_errors", 0);

require_once __DIR__ . '/../../auth.php';
require_login();

header("Content-Type: application/json");
header("Cache-Control: no-cache, must-revalidate");
require_once __DIR__ . '/../../db.php';

$username = $_SESSION['username'] ?? 'anonymous';
$jsonFile = __DIR__ . '/../../chat_history.json';

// Yardımcı: JSON dosyasından tüm sohbetleri oku
function getJsonHistory($jsonFile) {
    if (file_exists($jsonFile)) {
        $content = @file_get_contents($jsonFile);
        return $content ? (json_decode($content, true) ?? []) : [];
    }
    return [];
}

// Yardımcı: JSON dosyasına tüm sohbetleri yaz
function saveJsonHistory($jsonFile, $data) {
    @file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$inputData = json_decode(file_get_contents("php://input"), true);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($inputData && isset($inputData["url"])) {
        $chatId = (string)($inputData["chatId"] ?? time());
        $url = $inputData["url"];
        $type = $inputData["type"] ?? '';
        $messages = $inputData["messages"] ?? [];
        $completedSteps = $inputData["completedSteps"] ?? [];
        $reportData = $inputData["reportData"] ?? [];
        $fixedIssues = $inputData["fixedIssues"] ?? [];
        $date_str = date("d.m.Y H:i");

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO chat_history (username, chat_id, url, type, messages, completed_steps, report_data, fixed_issues, date_str) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE 
                                       username=VALUES(username), url=VALUES(url), type=VALUES(type), messages=VALUES(messages), 
                                       completed_steps=VALUES(completed_steps), report_data=VALUES(report_data), 
                                       fixed_issues=VALUES(fixed_issues), date_str=VALUES(date_str)");
                $stmt->execute([
                    $username, $chatId, $url, $type, 
                    json_encode($messages), json_encode($completedSteps), 
                    json_encode($reportData), json_encode($fixedIssues), $date_str
                ]);
            } catch (Exception $e) {
                error_log("save_chat.php DB save error: " . $e->getMessage());
            }
        }

        // Daima JSON dosyasına da yedekle
        $allJson = getJsonHistory($jsonFile);
        if (!isset($allJson[$username])) $allJson[$username] = [];
        $allJson[$username][$chatId] = [
            'chatId' => $chatId,
            'url' => $url,
            'type' => $type,
            'messages' => $messages,
            'completedSteps' => $completedSteps,
            'reportData' => $reportData,
            'fixedIssues' => $fixedIssues,
            'date' => $date_str
        ];
        saveJsonHistory($jsonFile, $allJson);

        echo json_encode(["success" => true, "chatId" => $chatId]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Geçersiz veri"]);
    }

} elseif ($_SERVER["REQUEST_METHOD"] === "GET") {
    $historyMap = [];

    // 1. Veritabanından Oku ($username ve 'anonymous' kayıtları)
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM chat_history WHERE username = ? OR username = 'anonymous' ORDER BY id ASC");
            $stmt->execute([$username]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $cid = (string)$row['chat_id'];
                $historyMap[$cid] = [
                    'chatId' => $cid,
                    'url' => $row['url'],
                    'type' => $row['type'],
                    'messages' => json_decode($row['messages'], true) ?? [],
                    'completedSteps' => json_decode($row['completed_steps'], true) ?? [],
                    'reportData' => json_decode($row['report_data'], true) ?? [],
                    'fixedIssues' => json_decode($row['fixed_issues'], true) ?? [],
                    'date' => $row['date_str']
                ];
            }
        } catch (Exception $e) {
            error_log("save_chat.php DB read error: " . $e->getMessage());
        }
    }

    // 2. JSON dosyasından oku ($username ve 'anonymous' kayıtları)
    $allJson = getJsonHistory($jsonFile);
    foreach ([$username, 'anonymous'] as $u) {
        if (isset($allJson[$u]) && is_array($allJson[$u])) {
            foreach ($allJson[$u] as $cid => $item) {
                $scid = (string)($item['chatId'] ?? $cid);
                if (!isset($historyMap[$scid])) {
                    $historyMap[$scid] = [
                        'chatId' => $scid,
                        'url' => $item['url'] ?? '',
                        'type' => $item['type'] ?? '',
                        'messages' => $item['messages'] ?? [],
                        'completedSteps' => $item['completedSteps'] ?? [],
                        'reportData' => $item['reportData'] ?? [],
                        'fixedIssues' => $item['fixedIssues'] ?? [],
                        'date' => $item['date'] ?? ''
                    ];
                }
            }
        }
    }

    // Listeyi tarihe göre tersten sırala (En yeni üstte)
    $historyList = array_values($historyMap);
    usort($historyList, function($a, $b) {
        return strcmp((string)$b['chatId'], (string)$a['chatId']);
    });

    echo json_encode(["history" => $historyList]);

} elseif ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    $targetId = $_GET["id"] ?? 'all';

    if ($pdo) {
        try {
            if ($targetId !== "all") {
                $stmt = $pdo->prepare("DELETE FROM chat_history WHERE (username = ? OR username = 'anonymous') AND chat_id = ?");
                $stmt->execute([$username, (string)$targetId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM chat_history WHERE username = ? OR username = 'anonymous'");
                $stmt->execute([$username]);
            }
        } catch (Exception $e) {
            error_log("save_chat.php DB delete error: " . $e->getMessage());
        }
    }

    // JSON dosyasından da sil
    $allJson = getJsonHistory($jsonFile);
    foreach ([$username, 'anonymous'] as $u) {
        if (isset($allJson[$u])) {
            if ($targetId !== "all") {
                unset($allJson[$u][$targetId]);
            } else {
                unset($allJson[$u]);
            }
        }
    }
    saveJsonHistory($jsonFile, $allJson);

    echo json_encode(["success" => true]);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}

<?php
error_reporting(0);
ini_set("display_errors", 0);
session_start();
header("Content-Type: application/json");
header("Cache-Control: no-cache, must-revalidate");
require_once __DIR__ . '/../../db.php';

$username = $_SESSION["username"] ?? "anonymous";
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($data && isset($data["url"])) {
        $chatId = (string)($data["chatId"] ?? time());
        
        $url = $data["url"];
        $type = $data["type"] ?? '';
        $messages = json_encode($data["messages"] ?? []);
        $completedSteps = json_encode($data["completedSteps"] ?? []);
        $reportData = json_encode($data["reportData"] ?? []);
        $fixedIssues = json_encode($data["fixedIssues"] ?? []);
        $date_str = date("d.m.Y H:i");

        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO chat_history (username, chat_id, url, type, messages, completed_steps, report_data, fixed_issues, date_str) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE 
                                   url=VALUES(url), type=VALUES(type), messages=VALUES(messages), 
                                   completed_steps=VALUES(completed_steps), report_data=VALUES(report_data), 
                                   fixed_issues=VALUES(fixed_issues), date_str=VALUES(date_str)");
            $stmt->execute([
                $username, $chatId, $url, $type, $messages, $completedSteps, $reportData, $fixedIssues, $date_str
            ]);
            echo json_encode(["success" => true, "chatId" => $chatId]);
        } else {
            echo json_encode(["error" => "Veritabanı bağlantısı yok"]);
        }
    } else {
        echo json_encode(["error" => "Geçersiz veri"]);
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "GET") {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM chat_history WHERE username = ? ORDER BY chat_id DESC");
        $stmt->execute([$username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'chatId' => $row['chat_id'],
                'url' => $row['url'],
                'type' => $row['type'],
                'messages' => json_decode($row['messages'], true),
                'completedSteps' => json_decode($row['completed_steps'], true),
                'reportData' => json_decode($row['report_data'], true),
                'fixedIssues' => json_decode($row['fixed_issues'], true),
                'date' => $row['date_str']
            ];
        }
        echo json_encode(["history" => $history]);
    } else {
        echo json_encode(["history" => []]);
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    if ($pdo) {
        if (isset($_GET["id"]) && $_GET["id"] !== "all") {
            $stmt = $pdo->prepare("DELETE FROM chat_history WHERE username = ? AND chat_id = ?");
            $stmt->execute([$username, (string)$_GET["id"]]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM chat_history WHERE username = ?");
            $stmt->execute([$username]);
        }
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Veritabanı bağlantısı yok"]);
    }
}
?>

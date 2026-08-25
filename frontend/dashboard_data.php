<?php
require_once __DIR__ . '/db.php';

$username = $_SESSION["username"] ?? "anonymous";
$dashboardData = [
    'totalAnalyses' => 0,
    'battleCount' => 0,
    'avgEEAT' => 0,
    'recent' => []
];

if ($pdo) {
    try {
        // Ensure trust_score column exists
        try {
            $pdo->exec("ALTER TABLE chat_history ADD COLUMN trust_score INT DEFAULT 0");
        } catch (PDOException $e) {}

        $stmt = $pdo->prepare("SELECT * FROM chat_history WHERE username = ? ORDER BY id DESC, chat_id DESC");
        $stmt->execute([$username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dashboardData['totalAnalyses'] = count($rows);
        $totalEeat = 0;
        $eeatCount = 0;

        foreach ($rows as $index => $row) {
            $messages = $row['messages'] ?? '';
            $cSteps = json_decode($row['completed_steps'], true) ?? [];
            $fIssues = json_decode($row['fixed_issues'], true) ?? [];
            
            if (stripos($messages, 'rakip') !== false || stripos($messages, 'battle') !== false) {
                $dashboardData['battleCount']++;
            }

            $trustScore = (int)($row['trust_score'] ?? 0);
            if ($trustScore == 0 && (in_array("4", $cSteps) || in_array(4, $cSteps))) {
                $trustScore = 70 + (count($fIssues) * 4);
                if ($trustScore > 98) $trustScore = 98;
            }

            if ($trustScore > 0) {
                $totalEeat += $trustScore;
                $eeatCount++;
            }

            if ($index < 5) {
                $health = "Orta";
                $color = "#eab308";
                $comp = count($cSteps);
                $fixes = count($fIssues);

                if ($trustScore >= 90) { $health = "Mükemmel"; $color = "#22c55e"; }
                else if ($trustScore > 0 && $trustScore < 60) { $health = "Kritik"; $color = "#ef4444"; }
                else if ($comp >= 5 && $fixes >= 3) { $health = "Mükemmel"; $color = "#22c55e"; }
                else if ($comp >= 3 && $fixes < 2) { $health = "Kritik"; $color = "#ef4444"; }

                $dashboardData['recent'][] = [
                    'url' => $row['url'],
                    'date' => $row['date_str'],
                    'health' => $health,
                    'color' => $color
                ];
            }
        }
        if ($eeatCount > 0) {
            $dashboardData['avgEEAT'] = round($totalEeat / $eeatCount);
        }

    } catch(PDOException $e) {}
}
?>

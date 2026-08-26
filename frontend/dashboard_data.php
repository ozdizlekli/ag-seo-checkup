<?php
/**
 * dashboard_data.php
 *
 * NOT: Dashboard istatistikleri (toplam analiz, E-E-A-T skoru vb.)
 * tamamen JavaScript tarafında hesaplanıyor (copilot.js → window.renderDashboard).
 * Bu PHP dosyası şu an aktif olarak kullanılmıyor.
 *
 * Eğer ileride SSR (sunucu taraflı render) gerekirse:
 *   1. index.php'nin PHP bloğuna aşağıdakini ekle:
 *      define('AGSEO_INTERNAL', true);
 *      require_once __DIR__ . '/dashboard_data.php';
 *   2. $dashboardData değişkenini Blade/PHP template'e aktar.
 *
 * MEVCUT DURUM: Bu dosya index.php'de require edilmiyor.
 * dashboard istatistikleri JS History'den (window.agChatHistory) okunuyor.
 *
 * GÜVENLİK: Doğrudan HTTP erişimi hâlâ engellidir (AGSEO_INTERNAL sabiti).
 */

if (!defined('AGSEO_INTERNAL')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    return;
}

$username      = $_SESSION['username'];
$dashboardData = [
    'totalAnalyses' => 0,
    'battleCount'   => 0,
    'avgEEAT'       => 0,
    'recent'        => [],
];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        try {
            $pdo->exec("ALTER TABLE chat_history ADD COLUMN trust_score INT DEFAULT 0");
        } catch (PDOException $e) {}

        $stmt = $pdo->prepare(
            "SELECT * FROM chat_history WHERE username = ? ORDER BY id DESC, chat_id DESC"
        );
        $stmt->execute([$username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dashboardData['totalAnalyses'] = count($rows);
        $totalEeat = 0;
        $eeatCount = 0;

        foreach ($rows as $index => $row) {
            $messages = $row['messages'] ?? '';
            $cSteps   = json_decode($row['completed_steps'], true) ?? [];
            $fIssues  = json_decode($row['fixed_issues'], true)    ?? [];

            if (stripos($messages, 'rakip') !== false || stripos($messages, 'battle') !== false) {
                $dashboardData['battleCount']++;
            }

            $trustScore = (int)($row['trust_score'] ?? 0);
            if ($trustScore === 0 && (in_array('4', $cSteps) || in_array(4, $cSteps))) {
                $trustScore = min(98, 70 + (count($fIssues) * 4));
            }

            if ($trustScore > 0) {
                $totalEeat += $trustScore;
                $eeatCount++;
            }

            if ($index < 5) {
                $health = 'Orta';
                $color  = '#eab308';
                $comp   = count($cSteps);
                $fixes  = count($fIssues);

                if ($trustScore >= 90)                           { $health = 'Mükemmel'; $color = '#22c55e'; }
                elseif ($trustScore > 0 && $trustScore < 60)    { $health = 'Kritik';   $color = '#ef4444'; }
                elseif ($comp >= 5 && $fixes >= 3)              { $health = 'Mükemmel'; $color = '#22c55e'; }
                elseif ($comp >= 3 && $fixes < 2)               { $health = 'Kritik';   $color = '#ef4444'; }

                $dashboardData['recent'][] = [
                    'url'    => $row['url'],
                    'date'   => $row['date_str'],
                    'health' => $health,
                    'color'  => $color,
                ];
            }
        }

        if ($eeatCount > 0) {
            $dashboardData['avgEEAT'] = round($totalEeat / $eeatCount);
        }

    } catch (PDOException $e) {
        error_log("dashboard_data.php DB hatası: " . $e->getMessage());
    }
}

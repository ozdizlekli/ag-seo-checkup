<?php
require_once __DIR__ . '/db.php';

try {
    // db.php zaten $pdo'yu oluşturuyor (veritabanı adı ile). 
    // Ancak veritabanı yoksa diye db.php'deki hatayı yoksayıp yeniden bağlanabiliriz:
    $pdo_setup = new PDO("mysql:host=$host;charset=utf8", $user, $pass);
    $pdo_setup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_setup->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo_setup->exec("USE `$dbname`");
    $pdo = $pdo_setup; // Replace main pdo
} catch (PDOException $e) {
    die("Veritabanı sunucusuna bağlanılamadı: " . $e->getMessage() . "\n");
}

echo "Veritabanı '$dbname' seçildi/oluşturuldu.\n";
echo "Tablolar oluşturuluyor...\n";

$sql = "
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain_url VARCHAR(255),
    drive_folder_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    keyword VARCHAR(255),
    old_text LONGTEXT,
    new_text LONGTEXT,
    project_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    keyword VARCHAR(255),
    opportunity_score INT DEFAULT 0,
    volume INT DEFAULT 0,
    difficulty INT DEFAULT 0,
    cpc DECIMAL(10,2) DEFAULT 0.00,
    intent VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NULL,
    content_score INT DEFAULT 0,
    keyword_score INT DEFAULT 0,
    technical_score INT DEFAULT 0,
    schema_score INT DEFAULT 0,
    offsite_score INT DEFAULT 0,
    overall_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    chat_id VARCHAR(50) NOT NULL,
    url VARCHAR(500),
    type VARCHAR(100),
    messages LONGTEXT,
    completed_steps LONGTEXT,
    report_data LONGTEXT,
    fixed_issues LONGTEXT,
    date_str VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (username, chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Tablolar başarıyla oluşturuldu veya zaten var.\n";
} catch (PDOException $e) {
    die("Tablo oluşturma hatası: " . $e->getMessage() . "\n");
}

echo "chat_history.json verileri MySQL'e aktarılıyor...\n";

$jsonFile = __DIR__ . '/chat_history.json';
if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true);
    if (is_array($data)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO chat_history (username, chat_id, url, type, messages, completed_steps, report_data, fixed_issues, date_str) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $count = 0;
        foreach ($data as $username => $chats) {
            foreach ($chats as $chatId => $chatData) {
                $url = $chatData['url'] ?? '';
                $type = $chatData['type'] ?? '';
                $messages = json_encode($chatData['messages'] ?? []);
                $completed_steps = json_encode($chatData['completedSteps'] ?? []);
                $report_data = json_encode($chatData['reportData'] ?? []);
                $fixed_issues = json_encode($chatData['fixedIssues'] ?? []);
                $date_str = $chatData['date'] ?? '';
                
                $stmt->execute([
                    $username,
                    (string)$chatId,
                    $url,
                    $type,
                    $messages,
                    $completed_steps,
                    $report_data,
                    $fixed_issues,
                    $date_str
                ]);
                $count++;
            }
        }
        echo "Toplam $count adet sohbet kaydı MySQL'e aktarıldı.\n";
    }
} else {
    echo "chat_history.json dosyası bulunamadı, aktarım atlandı.\n";
}

echo "Taşıma işlemi tamamlandı!\n";
?>

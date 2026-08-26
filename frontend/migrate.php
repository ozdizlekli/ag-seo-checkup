<?php
/**
 * migrate.php — Veritabanı kurulum ve veri taşıma aracı
 * 
 * GÜVENLİK: Bu script SADECE komut satırından (CLI) çalıştırılabilir.
 * Web üzerinden erişim tamamen engellenmiştir.
 * 
 * Kullanım: php migrate.php
 */

// Web üzerinden doğrudan erişimi engelle
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

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

CREATE TABLE IF NOT EXISTS technical_score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    client_id INT NULL,
    final_score INT DEFAULT 0,
    crawlability_score INT DEFAULT 0,
    performance_score INT DEFAULT 0,
    site_structure_score INT DEFAULT 0,
    security_score INT DEFAULT 0,
    schema_score INT DEFAULT 0,
    mobile_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Tablolar başarıyla oluşturuldu veya zaten var.\n";
} catch (PDOException $e) {
    die("Tablo oluşturma hatası: " . $e->getMessage() . "\n");
}

// technical_score_history tablosuna sonradan eklenen kolonlar (is_partial,
// is_full_crawl) - tablo migrate.php'nin ilk suruminde bunlar olmadan
// olusturuldugu icin zaten var olan kurulumlarda ALTER TABLE ile eklememiz
// gerekiyor. Idempotent olmasi icin once information_schema'dan kolonun var
// olup olmadigini kontrol ediyoruz (MySQL'in eski surumleri ADD COLUMN IF
// NOT EXISTS desteklemiyor).
$technicalScoreHistoryColumns = [
    'is_partial' => "ALTER TABLE technical_score_history ADD COLUMN is_partial TINYINT(1) NOT NULL DEFAULT 0 AFTER final_score",
    'is_full_crawl' => "ALTER TABLE technical_score_history ADD COLUMN is_full_crawl TINYINT(1) NOT NULL DEFAULT 0 AFTER is_partial",
    // 'domain' - url'nin normalize edilmis hostname'i (protokol/www/yol/
    // sorgu/sondaki slash yok sayilir). Bir URL'nin, kayitli bir musterinin
    // domain_url'iyle ayni siteye ait olup olmadigini ANLAMAK icin - boylece
    // "URL ile Ara" ve "Musteriye Gore" ayni site icin ayni gecmisi
    // gosterebiliyor (bkz. api/technical_score_history.php).
    'domain' => "ALTER TABLE technical_score_history ADD COLUMN domain VARCHAR(255) NULL AFTER url",
];
foreach ($technicalScoreHistoryColumns as $columnName => $alterSql) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'technical_score_history' AND COLUMN_NAME = ?");
        $stmt->execute([$columnName]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec($alterSql);
            echo "technical_score_history tablosuna $columnName kolonu eklendi.\n";
        }
    } catch (PDOException $e) {
        echo "$columnName kolonu eklenirken hata (yoksayilabilir): " . $e->getMessage() . "\n";
    }
}

// domain kolonunda arama yapilacagi icin (client_id = ? OR domain = ?) bir
// index faydali olur - yoksa her sorguda tam tablo taramasi olur.
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'technical_score_history' AND INDEX_NAME = 'idx_domain'");
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE technical_score_history ADD INDEX idx_domain (domain)");
        echo "technical_score_history.domain icin index eklendi.\n";
    }
} catch (PDOException $e) {
    echo "domain index'i eklenirken hata (yoksayilabilir): " . $e->getMessage() . "\n";
}

// Eskiden kaydedilmis satirlarin domain kolonu bos kalir (yukaridaki ALTER
// TABLE onlari NULL birakir) - bu satirlar geriye donuk olarak musteri-domain
// eslestirmesine dahil OLMAZ, halbuki tam da onlar icin bu ozellik istendi.
// Bu yuzden bos domain'leri, url'den ayni normalizasyon mantigiyla (bkz.
// api/technical_score_history.php normalizeDomain()) tek seferlik geriye
// donuk dolduruyoruz.
function migrateNormalizeDomain(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }
    $host = strtolower(trim($host));
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }
    return $host;
}

try {
    $rows = $pdo->query("SELECT id, url FROM technical_score_history WHERE domain IS NULL OR domain = ''")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $updateStmt = $pdo->prepare("UPDATE technical_score_history SET domain = ? WHERE id = ?");
        $backfilled = 0;
        foreach ($rows as $row) {
            $domain = migrateNormalizeDomain((string) $row['url']);
            if ($domain !== '') {
                $updateStmt->execute([$domain, $row['id']]);
                $backfilled++;
            }
        }
        echo "technical_score_history: $backfilled eski kayit icin domain geriye donuk dolduruldu.\n";
    }
} catch (PDOException $e) {
    echo "domain geriye donuk doldurma hatasi (yoksayilabilir): " . $e->getMessage() . "\n";
}

// chat_history.json varsa ve veri aktarımı isteniyorsa
$jsonFile = __DIR__ . '/chat_history.json';
if (file_exists($jsonFile)) {
    echo "chat_history.json verileri MySQL'e aktarılıyor...\n";
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
        echo "NOT: chat_history.json artık güvenli değil — repoya commit etmeyin ve silin.\n";
    }
} else {
    echo "chat_history.json dosyası bulunamadı, aktarım atlandı.\n";
}

echo "Taşıma işlemi tamamlandı!\n";

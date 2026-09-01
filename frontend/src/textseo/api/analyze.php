<?php
/**
 * SEO Metin Optimizasyonu — Ana Analiz Endpoint'i
 * POST /api/analyze.php
 * Body: {"url": "https://ornek.com/sayfa"}
 */

// Uzun süren işlem için zaman limitini artır (5 dakika)
set_time_limit(300);

// Gerekli dosyaları yükle
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../../db.php';

// Response header'ları
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_ENV['ALLOWED_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Authorization');

// OPTIONS preflight için erken çıkış
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Sadece POST kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'Sadece POST istekleri kabul edilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once __DIR__ . '/../includes/Scraper.php';
require_once __DIR__ . '/../includes/TextAnalyzer.php';
require_once __DIR__ . '/../includes/GeminiClient.php';
require_once __DIR__ . '/../includes/AuthMiddleware.php';

AuthMiddleware::verify();

try {
    // 1. POST verisini al
    $input = json_decode(file_get_contents('php://input'), true);
    $url = trim($input['url'] ?? '');
    $url = rtrim($url, '/');

    // 2. URL validasyonu
    if (empty($url)) {
        throw new InvalidArgumentException('URL boş olamaz.');
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Geçerli bir URL giriniz.');
    }
    if (!preg_match('/^https?:\/\//i', $url)) {
        throw new InvalidArgumentException('URL http:// veya https:// ile başlamalıdır.');
    }

    // SSRF Koruması
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        throw new InvalidArgumentException('URL\'den geçerli bir host bilgisi çıkarılamadı.');
    }
    $ips = gethostbynamel($host);
    if (!$ips) {
        throw new InvalidArgumentException('Host adresi çözümlenemedi: ' . $host);
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Dahili (private/reserved) IP adreslerine istek yapılamaz.');
        }
    }

    // DB Tablosu yoksa oluştur
    if ($pdo) {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS text_seo_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            url VARCHAR(500) NOT NULL,
            analysis_number INT DEFAULT 1,
            is_reanalysis TINYINT(1) DEFAULT 0,
            used_keywords LONGTEXT,
            original_data LONGTEXT,
            optimized_data LONGTEXT,
            analysis_data LONGTEXT,
            keywords_data LONGTEXT,
            debug_trace LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    // 3. Sayfayı çek
    $scraper = new Scraper();
    $scraped = $scraper->scrape($url);

    if ($scraped['status'] === 'error') {
        throw new RuntimeException('Sayfa çekilemedi: ' . $scraped['error']);
    }
    
    $analyzer = new TextAnalyzer();
    $gemini = new GeminiClient();
    
    // DB Kontrolü (DURUM A veya DURUM B)
    $isReanalysis = 0;
    $analysisNumber = 1;
    $usedKeywords = [];
    $historyId = 0;
    $lastRecord = null;
    $warnings = [];

    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id, analysis_number, used_keywords, optimized_data, keywords_data FROM text_seo_history WHERE url = :url ORDER BY id DESC LIMIT 1");
        $stmt->execute(['url' => $url]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $lastRecord = $row;
            $isReanalysis = 1;
            $analysisNumber = (int)$row['analysis_number'] + 1;
            $historyId = $row['id'];
            $usedKeywords = json_decode($row['used_keywords'], true) ?: [];
        }
    } else {
        $warnings[] = "Veritabanı bağlantısı yok, analiz sıfırdan başlatılıyor";
    }

    $originalData = [
        'title'       => $scraped['title'],
        'description' => $scraped['description'],
        'body_text'   => $scraped['body_text'],
        'headings'    => $scraped['headings'],
        'word_count'  => $scraped['word_count']
    ];

    if ($isReanalysis) {
        // DURUM B: Tekrar Analiz
        $lastOptimized = json_decode($lastRecord['optimized_data'], true) ?: [];
        $lastKeywords = json_decode($lastRecord['keywords_data'], true) ?: [];
        
        $excludedKeywords = array_filter(array_merge(
            [$lastKeywords['focus'] ?? ''],
            $lastKeywords['secondary'] ?? []
        ));
        
        $inputForReOpt = [
            'title'       => !empty($lastOptimized['title']) ? $lastOptimized['title'] : $scraped['title'],
            'description' => !empty($lastOptimized['description']) ? $lastOptimized['description'] : $scraped['description'],
            'body_text'   => !empty($lastOptimized['body_text']) ? $lastOptimized['body_text'] : $scraped['body_text'],
            'headings'    => $scraped['headings'],
            'word_count'  => $scraped['word_count']
        ];
        
        // Karşılaştırma tabanını (originalData) N-1'in çıktısı olacak şekilde güncelle
        $originalData = $inputForReOpt;
        
        $reOptResult = $gemini->quickReOptimizeWithDifferentKeywords($inputForReOpt, $excludedKeywords);
        
        $keywords = [
            'focus'          => $reOptResult['focus'] ?? '',
            'secondary'      => $reOptResult['secondary'] ?? [],
            'intent'         => $reOptResult['intent'] ?? 'bilgi alma',
            'topic_summary'  => $reOptResult['topic_summary'] ?? '',
            'missing_topics' => $reOptResult['missing_topics'] ?? []
        ];
        
        $optimized = [
            'title'       => $reOptResult['title'] ?? $originalData['title'],
            'description' => $reOptResult['description'] ?? $originalData['description'],
            'body_text'   => $reOptResult['body_text'] ?? $originalData['body_text']
        ];
        
        // Yeni analizi TextAnalyzer ile yap (sonuçları görmek için)
        // Optimizasyon sonrası olduğu için keywords'e göre yapıyoruz
        // Fake scraped data with optimized text to get a post-optimization analysis
        $scrapedForAnalysis = $scraped;
        $scrapedForAnalysis['title'] = $optimized['title'] ?? $scraped['title'];
        $scrapedForAnalysis['description'] = $optimized['description'] ?? $scraped['description'];
        $scrapedForAnalysis['body_text'] = $optimized['body_text'] ?? $scraped['body_text'];
        
        $fullAnalysis = $analyzer->analyze($scrapedForAnalysis, $keywords);
        
    } else {
        // DURUM A: İlk Analiz
        $initialAnalysis = $analyzer->analyze($scraped);
        $keywords = $gemini->discoverKeywords(
            $scraped['title'],
            $scraped['description'],
            $scraped['headings'],
            $scraped['body_text']
        );
        $fullAnalysis = $analyzer->analyze($scraped, $keywords);
        
        $optimized = $gemini->optimizeContent(
            $originalData,
            $fullAnalysis,
            $keywords
        );
    }
    
    // Yeni kullanılan kelimeleri havuza ekle
    $currentFocus = $keywords['focus'] ?? '';
    if (!empty($currentFocus) && !in_array($currentFocus, $usedKeywords)) {
        $usedKeywords[] = $currentFocus;
    }
    if (!empty($keywords['secondary']) && is_array($keywords['secondary'])) {
        foreach ($keywords['secondary'] as $secKw) {
            if (!empty($secKw) && !in_array($secKw, $usedKeywords)) {
                $usedKeywords[] = $secKw;
            }
        }
    }
    
    // DB'ye kaydet
    if ($pdo) {
        $stmt = $pdo->prepare("
            INSERT INTO text_seo_history 
            (url, analysis_number, is_reanalysis, used_keywords, original_data, optimized_data, analysis_data, keywords_data, debug_trace)
            VALUES 
            (:url, :num, :re, :used, :orig, :opt, :ana, :kw, :dbg)
        ");
        $stmt->execute([
            'url' => $url,
            'num' => $analysisNumber,
            're' => $isReanalysis,
            'used' => json_encode($usedKeywords, JSON_UNESCAPED_UNICODE),
            'orig' => json_encode($originalData, JSON_UNESCAPED_UNICODE),
            'opt' => json_encode($optimized, JSON_UNESCAPED_UNICODE),
            'ana' => json_encode($fullAnalysis, JSON_UNESCAPED_UNICODE),
            'kw' => json_encode($keywords, JSON_UNESCAPED_UNICODE),
            'dbg' => json_encode($gemini->debugTrace, JSON_UNESCAPED_UNICODE)
        ]);
    } else {
        $warnings[] = "Sonuçlar kaydedilemedi, tekrar analiz (chaining) çalışmayacaktır";
    }

    $warnings = array_merge($warnings, $gemini->warnings ?? []);

    // 8. Başarılı sonuç
    echo json_encode([
        'status'          => 'success',
        'warnings'        => $warnings,
        'is_reanalysis'   => (bool)$isReanalysis,
        'analysis_number' => $analysisNumber,
        'original'        => $originalData,
        'optimized'       => [
            'title'       => $optimized['title'] ?? $originalData['title'],
            'description' => $optimized['description'] ?? $originalData['description'],
            'body_text'   => $optimized['body_text'] ?? $originalData['body_text']
        ],
        'analysis'        => $fullAnalysis,
        'keywords'        => $keywords,
        'debug_trace'     => $gemini->debugTrace
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[SEO Analyzer] Beklenmeyen hata: ' . $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['status' => 'error', 'error' => 'Beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.'], JSON_UNESCAPED_UNICODE);
}

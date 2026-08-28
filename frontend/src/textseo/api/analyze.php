<?php
/**
 * SEO Metin Optimizasyonu — Ana Analiz Endpoint'i
 * POST /api/analyze.php
 * Body: {"url": "https://ornek.com/sayfa"}
 */

// Uzun süren işlem için zaman limitini artır (5 dakika)
set_time_limit(300);

// Response header'ları
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

// Gerekli dosyaları yükle
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Scraper.php';
require_once __DIR__ . '/../includes/TextAnalyzer.php';
require_once __DIR__ . '/../includes/GeminiClient.php';

try {
    // 1. POST verisini al
    $input = json_decode(file_get_contents('php://input'), true);
    $url = trim($input['url'] ?? '');

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

    // 3. Sayfayı çek
    $scraper = new Scraper();
    $scraped = $scraper->scrape($url);

    if ($scraped['status'] === 'error') {
        throw new RuntimeException('Sayfa çekilemedi: ' . $scraped['error']);
    }

    // 4. İlk analiz (keywords boş — henüz keşfedilmedi)
    $analyzer = new TextAnalyzer();
    $initialAnalysis = $analyzer->analyze($scraped);

    // 5. Gemini Aşama A: Anahtar kelime keşfi
    $gemini = new GeminiClient();
    $keywords = $gemini->discoverKeywords(
        $scraped['title'],
        $scraped['description'],
        $scraped['headings'],
        $scraped['body_text']
    );

    // 6. İkinci analiz (keywords dolu — tam eksiklik raporu)
    $fullAnalysis = $analyzer->analyze($scraped, $keywords);

    // 7. Gemini Aşama B: Metin optimizasyonu
    $optimized = $gemini->optimizeContent(
        [
            'title'       => $scraped['title'],
            'description' => $scraped['description'],
            'body_text'   => $scraped['body_text'],
            'word_count'  => $scraped['word_count']
        ],
        $fullAnalysis,
        $keywords
    );

    // 8. Başarılı sonuç
    echo json_encode([
        'status'    => 'success',
        'original'  => [
            'title'       => $scraped['title'],
            'description' => $scraped['description'],
            'body_text'   => $scraped['body_text'],
            'headings'    => $scraped['headings'],
            'word_count'  => $scraped['word_count']
        ],
        'optimized' => [
            'title'       => $optimized['title'] ?? $scraped['title'],
            'description' => $optimized['description'] ?? $scraped['description'],
            'body_text'   => $optimized['body_text'] ?? $scraped['body_text']
        ],
        'analysis'  => $fullAnalysis,
        'keywords'  => $keywords,
        'debug_trace' => $gemini->debugTrace
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

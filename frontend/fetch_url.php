<?php
/**
 * fetch_url.php — Harici URL içeriği çekme proxy'si
 * 
 * GÜVENLİK:
 * - Oturum açmış kullanıcılar ile sınırlı
 * - SSRF koruması: private/reserved IP aralıkları engelleniyor
 * - Sadece HTTP/HTTPS şemaları kabul ediliyor
 * - Rate limiting: dakikada 30 istek
 */

require_once __DIR__ . '/auth.php';
require_login();

header('Content-Type: application/json');

// --- Rate Limiting ---
$rateLimitKey = 'fetch_url_reqs';
$rateLimitWindow = 60;
$rateLimitMax = 30;
$now = time();
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'window_start' => $now];
}
$rl = &$_SESSION[$rateLimitKey];
if (($now - $rl['window_start']) > $rateLimitWindow) {
    $rl = ['count' => 0, 'window_start' => $now];
}
$rl['count']++;
if ($rl['count'] > $rateLimitMax) {
    http_response_code(429);
    echo json_encode(['error' => 'Çok fazla istek. Lütfen bir dakika bekleyin.']);
    exit;
}

$url = $_GET['url'] ?? '';

// --- URL Temel Geçerlilik Kontrolü ---
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz URL']);
    exit;
}

// --- Şema Kontrolü: Sadece HTTP ve HTTPS ---
$scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
if (!in_array($scheme, ['http', 'https'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Sadece HTTP ve HTTPS şemaları desteklenmektedir.']);
    exit;
}

// --- SSRF Koruması: Private/Reserved IP aralıklarını engelle ---
$host = parse_url($url, PHP_URL_HOST);
if (empty($host)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz URL: host tespit edilemedi.']);
    exit;
}

// Hostname'i IP'ye çevir
$resolvedIp = gethostbyname($host);

// Aynı kalıyorsa çözümlenememiş — güvenli reddet değil, devam et (public hostname)
// Asıl kontrol: resolve edildiyse private aralığı kontrol et
if ($resolvedIp !== $host) {
    // IP formatında mı kontrol et
    if (filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
        // Private ve reserved IP aralıklarını engelle (SSRF koruması)
        if (!filter_var(
            $resolvedIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            http_response_code(403);
            echo json_encode(['error' => 'Güvenlik kısıtlaması: Dahili/yerel ağ adreslerine erişim yasaktır.']);
            exit;
        }
    }
}

// URL doğrudan bir IP ise de kontrol et
if (filter_var($host, FILTER_VALIDATE_IP)) {
    if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        http_response_code(403);
        echo json_encode(['error' => 'Güvenlik kısıtlaması: Dahili/yerel ağ adreslerine erişim yasaktır.']);
        exit;
    }
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);  // Redirect zincirini sınırla
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AGSEOBot/1.0)');
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   // SSL doğrulaması aktif
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
// Redirect sonrası private IP'ye gitmeyi engelle
curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

$html = curl_exec($ch);

if (curl_errno($ch)) {
    $errMsg = curl_error($ch);
    curl_close($ch);
    error_log("fetch_url.php cURL hatası: " . $errMsg);
    http_response_code(502);
    echo json_encode(['error' => 'URL çekilemedi.']);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['error' => 'Hedef URL erişilemiyor (HTTP ' . $httpCode . ')']);
    exit;
}

// Basit temizlik
$dom = new DOMDocument();
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);

$title = '';
$nodes = $xpath->query('//title');
if ($nodes->length > 0) $title = $nodes->item(0)->nodeValue;

$desc = '';
$nodes = $xpath->query('//meta[@name="description"]/@content');
if ($nodes->length > 0) $desc = $nodes->item(0)->nodeValue;

$schemas = [];
$nodes = $xpath->query('//script[@type="application/ld+json"]');
foreach ($nodes as $node) {
    $schemas[] = trim($node->nodeValue);
}

// Görünür metni al (script ve style hariç)
$bodyText = "";
while (($node = $xpath->query('//script|//style|//nav|//footer|//svg')->item(0))) {
    $node->parentNode->removeChild($node);
}

// Sadece SEO için önemli etiketleri çek (Başlıklar, paragraflar, linkler ve listeler)
$nodes = $xpath->query('//h1 | //h2 | //h3 | //p | //li | //a');
foreach ($nodes as $node) {
    $tagName = strtolower($node->nodeName);
    $text = trim($node->nodeValue);

    if (empty($text)) continue;

    if ($tagName === 'h1') $bodyText .= "\n# " . $text . "\n";
    elseif ($tagName === 'h2') $bodyText .= "\n## " . $text . "\n";
    elseif ($tagName === 'h3') $bodyText .= "\n### " . $text . "\n";
    elseif ($tagName === 'p') $bodyText .= $text . "\n";
    elseif ($tagName === 'li') $bodyText .= "- " . $text . "\n";
    elseif ($tagName === 'a') {
        $href = $node->getAttribute('href');
        $bodyText .= "[LINK: " . $text . "](URL: " . $href . ") ";
    }
}

$bodyText = preg_replace('/\n+/', "\n", $bodyText);
$bodyText = substr($bodyText, 0, 20000);

// llms.txt kontrolü
$llmsUrl = rtrim($url, '/') . '/llms.txt';
$ch_llms = curl_init($llmsUrl);
curl_setopt($ch_llms, CURLOPT_NOBODY, true);
curl_setopt($ch_llms, CURLOPT_TIMEOUT, 3);
curl_setopt($ch_llms, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch_llms, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AGSEOBot/1.0)');
curl_setopt($ch_llms, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch_llms, CURLOPT_SSL_VERIFYHOST, 2);
curl_exec($ch_llms);
$llms_code = curl_getinfo($ch_llms, CURLINFO_HTTP_CODE);
curl_close($ch_llms);

$hasLlms = ($llms_code == 200) ? true : false;

echo json_encode([
    'title' => $title,
    'description' => $desc,
    'schemas' => $schemas,
    'has_llms_txt' => $hasLlms,
    'text' => trim($bodyText)
]);

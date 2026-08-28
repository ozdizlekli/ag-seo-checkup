<?php
/**
 * fetch_url.php — Harici URL içeriği çekme proxy'si
 *
 * GÜVENLİK:
 * - Oturum açmış kullanıcılar ile sınırlı
 * - SSRF koruması: DNS rebinding + redirect takibinde her hop'ta IP kontrolü
 *   CURLOPT_FOLLOWLOCATION KAPALI — redirect'ler elle takip edilip her
 *   hedef URL tekrar IP doğrulamasından geçiriliyor.
 * - CURLOPT_RESOLVE ile çözümlenen IP sabitlenip curl'ün kendi DNS
 *   sorgusunu yapması engelleniyor (DNS rebinding vektörü kapatılıyor).
 * - Sadece HTTP/HTTPS şemaları
 * - Rate limiting: dakikada 30 istek
 */

require_once __DIR__ . '/auth.php';
require_login();

header('Content-Type: application/json');

// --- Rate Limiting ---
$rateLimitKey    = 'fetch_url_reqs';
$rateLimitWindow = 60;
$rateLimitMax    = 30;
$now             = time();

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

// --------------------------------------------------------------------------
// SSRF yardımcı fonksiyonları
// --------------------------------------------------------------------------

/**
 * Verilen hostname'i DNS üzerinden çözer ve private/reserved aralığını
 * kontrol eder. Sorunluysa false, güvenliyse çözümlenen IP'yi döner.
 */
function resolveAndValidateHost(string $host): string|false
{
    // Doğrudan IP girildiyse yine kontrol et
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false; // Private/reserved IP
        }
        return $host; // Public IP, geçerli
    }

    // Hostname → IP
    $resolved = gethostbyname($host);

    // Çözümlenemedi (hostname aynı kaldı) → reddet
    if ($resolved === $host) {
        return false;
    }

    // Çözümlendi ama IP formatında değil → reddet
    if (!filter_var($resolved, FILTER_VALIDATE_IP)) {
        return false;
    }

    // Private/reserved IP mi?
    if (!filter_var($resolved, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }

    return $resolved; // Doğrulandı, güvenli public IP
}

/**
 * URL şemasını ve host'unu doğrular; başarılıysa çözümlenen IP'yi döner.
 * Herhangi bir sorun varsa hata mesajıyla beraber null döner.
 */
function validateUrl(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['error' => 'Geçersiz URL formatı.'];
    }

    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['error' => 'Sadece HTTP ve HTTPS şemaları desteklenmektedir.'];
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (empty($host)) {
        return ['error' => 'Geçersiz URL: host tespit edilemedi.'];
    }

    // IPv6 köşeli parantezlerini temizle
    $host = trim($host, '[]');

    $resolvedIp = resolveAndValidateHost($host);
    if ($resolvedIp === false) {
        return ['error' => 'Güvenlik kısıtlaması: Dahili/yerel ağ adreslerine erişim yasaktır.'];
    }

    return ['ok' => true, 'host' => $host, 'ip' => $resolvedIp];
}

// --------------------------------------------------------------------------
// İstek parametresi — başlangıç URL doğrulama
// --------------------------------------------------------------------------
$url = $_GET['url'] ?? '';

$validation = validateUrl($url);
if (isset($validation['error'])) {
    http_response_code(400);
    echo json_encode(['error' => $validation['error']]);
    exit;
}

$resolvedIp = $validation['ip'];
$host       = $validation['host'];
$port       = parse_url($url, PHP_URL_PORT)
    ?? (strtolower(parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);

// --------------------------------------------------------------------------
// SSRF Düzeltme 1: CURLOPT_RESOLVE ile DNS sabitleme
// curl kendi DNS sorgusunu YAPMAZ — kontrol ettiğimiz IP kullanılır.
// DNS rebinding vektörü bu şekilde kapatılır.
// --------------------------------------------------------------------------
$resolveEntry = "{$host}:{$port}:{$resolvedIp}";

// --------------------------------------------------------------------------
// SSRF Düzeltme 2: FOLLOWLOCATION KAPALI — redirect'ler elle takip edilir
// Her hop'ta validateUrl() ile IP tekrar doğrulanır.
// --------------------------------------------------------------------------
$maxRedirects  = 5;
$redirectCount = 0;
$currentUrl    = $url;
$currentHost   = $host;
$currentPort   = $port;
$currentIp     = $resolvedIp;
$html          = null;
$httpCode      = null;

while (true) {
    $resolveEntry = "{$currentHost}:{$currentPort}:{$currentIp}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $currentUrl);
    curl_setopt($ch, CURLOPT_RESOLVE, [$resolveEntry]);   // DNS sabitlendi
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);       // Redirect elle yönetiliyor
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AGSEOBot/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_HEADER, true);               // Header'ı yanıta dahil et

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $errMsg = curl_error($ch);
        curl_close($ch);
        error_log("fetch_url.php cURL hatası: " . $errMsg);
        http_response_code(502);
        echo json_encode(['error' => 'URL çekilemedi (cURL Hatası): ' . $errMsg]);
        exit;
    }

    $httpCode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseBody  = substr($response, $headerSize);
    $responseHeaders = substr($response, 0, $headerSize);
    curl_close($ch);

    // Redirect kontrolü (3xx)
    if ($httpCode >= 300 && $httpCode < 400) {
        if ($redirectCount >= $maxRedirects) {
            http_response_code(502);
            echo json_encode(['error' => 'Çok fazla yönlendirme.']);
            exit;
        }
        $redirectCount++;

        // Location header'ını çıkar
        preg_match('/^Location:\s*(.+)$/im', $responseHeaders, $matches);
        $location = isset($matches[1]) ? trim($matches[1]) : null;

        if (!$location) {
            http_response_code(502);
            echo json_encode(['error' => 'Geçersiz yönlendirme yanıtı.']);
            exit;
        }

        // Göreli URL'yi mutlak yap
        if (!preg_match('#^https?://#i', $location)) {
            $base = parse_url($currentUrl);
            $scheme = $base['scheme'] ?? 'https';
            $baseHost = $base['host'] ?? '';
            $location = $scheme . '://' . $baseHost . '/' . ltrim($location, '/');
        }

        // === HER REDIRECT HOP'UNDA IP DOĞRULAMASI ===
        $nextValidation = validateUrl($location);
        if (isset($nextValidation['error'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Yönlendirme hedefi güvenli değil: ' . $nextValidation['error']]);
            exit;
        }

        $currentUrl  = $location;
        $currentHost = $nextValidation['host'];
        $currentIp   = $nextValidation['ip'];
        $currentPort = parse_url($location, PHP_URL_PORT)
            ?? (strtolower(parse_url($location, PHP_URL_SCHEME)) === 'https' ? 443 : 80);

        continue; // Döngüye devam et — yeni URL ile tekrar çek
    }

    // 2xx yanıt — döngüden çık
    $html = $responseBody;
    break;
}

if ($httpCode < 200 || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['error' => 'Hedef URL erişilemiyor (HTTP ' . $httpCode . ')']);
    exit;
}

// --------------------------------------------------------------------------
// HTML parse ve SEO veri çıkarımı
// --------------------------------------------------------------------------
$dom = new DOMDocument();
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);

$title = '';
$nodes = $xpath->query('//title');
if ($nodes->length > 0) $title = $nodes->item(0)->nodeValue;

$desc  = '';
$nodes = $xpath->query('//meta[@name="description"]/@content');
if ($nodes->length > 0) $desc = $nodes->item(0)->nodeValue;

$schemas = [];
$nodes   = $xpath->query('//script[@type="application/ld+json"]');
foreach ($nodes as $node) {
    $schemas[] = trim($node->nodeValue);
}

while (($node = $xpath->query('//script|//style|//nav|//footer|//svg')->item(0))) {
    $node->parentNode->removeChild($node);
}

$bodyText = '';
$nodes    = $xpath->query('//h1 | //h2 | //h3 | //p | //li | //a');
foreach ($nodes as $node) {
    $tagName = strtolower($node->nodeName);
    $text    = trim($node->nodeValue);
    if (empty($text)) continue;

    if ($tagName === 'h1')      $bodyText .= "\n# " . $text . "\n";
    elseif ($tagName === 'h2')  $bodyText .= "\n## " . $text . "\n";
    elseif ($tagName === 'h3')  $bodyText .= "\n### " . $text . "\n";
    elseif ($tagName === 'p')   $bodyText .= $text . "\n";
    elseif ($tagName === 'li')  $bodyText .= "- " . $text . "\n";
    elseif ($tagName === 'a') {
        $href      = $node->getAttribute('href');
        $bodyText .= "[LINK: " . $text . "](URL: " . $href . ") ";
    }
}

$bodyText = preg_replace('/\n+/', "\n", $bodyText);
$bodyText = substr($bodyText, 0, 20000);

// --------------------------------------------------------------------------
// llms.txt kontrolü — aynı SSRF korumasıyla
// --------------------------------------------------------------------------
$llmsRawUrl = rtrim($url, '/') . '/llms.txt';
$llmsVal    = validateUrl($llmsRawUrl);
$hasLlms    = false;

if (!isset($llmsVal['error'])) {
    $llmsHost     = $llmsVal['host'];
    $llmsIp       = $llmsVal['ip'];
    $llmsPort     = parse_url($llmsRawUrl, PHP_URL_PORT)
        ?? (strtolower(parse_url($llmsRawUrl, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
    $llmsResolve  = "{$llmsHost}:{$llmsPort}:{$llmsIp}";

    $ch_llms = curl_init($llmsRawUrl);
    curl_setopt($ch_llms, CURLOPT_NOBODY, true);
    curl_setopt($ch_llms, CURLOPT_RESOLVE, [$llmsResolve]);
    curl_setopt($ch_llms, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch_llms, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch_llms, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AGSEOBot/1.0)');
    curl_setopt($ch_llms, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch_llms, CURLOPT_SSL_VERIFYHOST, 0);
    curl_exec($ch_llms);
    $llms_code = curl_getinfo($ch_llms, CURLINFO_HTTP_CODE);
    curl_close($ch_llms);
    $hasLlms = ($llms_code === 200);
}

echo json_encode([
    'title'        => $title,
    'description'  => $desc,
    'schemas'      => $schemas,
    'has_llms_txt' => $hasLlms,
    'text'         => trim($bodyText),
]);

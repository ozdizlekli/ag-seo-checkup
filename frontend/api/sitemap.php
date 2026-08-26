<?php
/**
 * api/sitemap.php — Müşteri kök domain'i için sitemap.xml'i güvenli şekilde
 * çeker, ayrıştırır ve sayfa URL'lerinin düz bir JSON listesini döner.
 *
 * Sidebar'daki "Sitemap Explorer" widget'ı (js/app.js -> loadClientSitemap)
 * bu endpoint'i kullanır. Daha önce bu widget var olmayan bir
 * http://localhost:3000/api/sitemap Node servisine istek atıyordu - proje
 * pure PHP olduğu için o servis hiç yoktu ve widget her zaman "Failed to
 * fetch" hatası veriyordu. Bu dosya o servisin yerini tamamen alıyor.
 *
 * Sitemap index dosyalarını (birden fazla alt sitemap'e işaret eden) da
 * destekler: her alt sitemap tek tek çekilip <url><loc> girdileri
 * birleştirilir. Sonsuz döngüye / aşırı yüke girmemek için hem taranan alt
 * sitemap sayısı hem de toplam URL sayısı sınırlandırılmıştır.
 *
 * Güvenlik: fetch_url.php'deki ile aynı SSRF koruması uygulanır (DNS
 * rebinding + redirect'lerin her hop'ta yeniden IP doğrulaması). Bu iki
 * dosya birbirinden bağımsız, kendi içinde tam korumalı script'ler olarak
 * tasarlandı (fetch_url.php üst seviyede $_GET işleyip exit ettiği için
 * doğrudan include edilip fonksiyonları yeniden kullanılamıyor).
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../src/TechnicalSeo/OnPageIndexabilityChecker.php';

use App\TechnicalSeo\OnPageIndexabilityChecker;

header('Content-Type: application/json');

// --------------------------------------------------------------------------
// Rate Limiting (fetch_url.php ile aynı desen)
// --------------------------------------------------------------------------
$rateLimitKey    = 'sitemap_api_reqs';
$rateLimitWindow = 60;
$rateLimitMax    = 20;
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

function sitemapResolveAndValidateHost(string $host): string|false
{
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false; // Private/reserved IP
        }
        return $host;
    }

    $resolved = gethostbyname($host);
    if ($resolved === $host) {
        return false; // Çözümlenemedi
    }
    if (!filter_var($resolved, FILTER_VALIDATE_IP)) {
        return false;
    }
    if (!filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false; // Private/reserved IP
    }

    return $resolved;
}

/**
 * @return array{ok?: true, host?: string, ip?: string, error?: string}
 */
function sitemapValidateUrl(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['error' => 'Geçersiz URL formatı.'];
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['error' => 'Sadece HTTP ve HTTPS şemaları desteklenmektedir.'];
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (empty($host)) {
        return ['error' => 'Geçersiz URL: host tespit edilemedi.'];
    }

    $host = trim($host, '[]');

    $resolvedIp = sitemapResolveAndValidateHost($host);
    if ($resolvedIp === false) {
        return ['error' => 'Güvenlik kısıtlaması: Dahili/yerel ağ adreslerine erişim yasaktır.'];
    }

    return ['ok' => true, 'host' => $host, 'ip' => $resolvedIp];
}

/**
 * SSRF korumalı GET isteği: redirect'ler elle takip edilir, her hop'ta IP
 * tekrar doğrulanır (DNS rebinding kapatılır). Başarısızsa null döner.
 */
function sitemapSafeFetch(string $url): ?string
{
    $validation = sitemapValidateUrl($url);
    if (isset($validation['error'])) {
        return null;
    }

    $currentUrl  = $url;
    $currentHost = $validation['host'];
    $currentIp   = $validation['ip'];
    $currentPort = parse_url($url, PHP_URL_PORT)
        ?? (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);

    $maxRedirects  = 5;
    $redirectCount = 0;

    while (true) {
        $resolveEntry = "{$currentHost}:{$currentPort}:{$currentIp}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $currentUrl);
        curl_setopt($ch, CURLOPT_RESOLVE, [$resolveEntry]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AGSEOBot/1.0)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            // PHP 8.0'dan beri curl handle'lar otomatik temizleniyor;
            // curl_close() PHP 8.5'te "deprecated" uyarısı üretiyor ve bu
            // uyarı display_errors açıkken JSON çıktısının önüne ekleniyor
            // ("Unexpected token '<'" hatasına yol açıyordu) - bu yüzden
            // hiç çağırmıyoruz.
            return null;
        }

        $httpCode        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize      = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseBody    = substr($response, $headerSize);
        $responseHeaders = substr($response, 0, $headerSize);

        if ($httpCode >= 300 && $httpCode < 400) {
            if ($redirectCount >= $maxRedirects) {
                return null;
            }
            $redirectCount++;

            preg_match('/^Location:\s*(.+)$/im', $responseHeaders, $matches);
            $location = isset($matches[1]) ? trim($matches[1]) : null;
            if (!$location) {
                return null;
            }

            if (!preg_match('#^https?://#i', $location)) {
                $base     = parse_url($currentUrl);
                $scheme   = $base['scheme'] ?? 'https';
                $baseHost = $base['host'] ?? '';
                $location = $scheme . '://' . $baseHost . '/' . ltrim($location, '/');
            }

            $nextValidation = sitemapValidateUrl($location);
            if (isset($nextValidation['error'])) {
                return null;
            }

            $currentUrl  = $location;
            $currentHost = $nextValidation['host'];
            $currentIp   = $nextValidation['ip'];
            $currentPort = parse_url($location, PHP_URL_PORT)
                ?? (strtolower((string) parse_url($location, PHP_URL_SCHEME)) === 'https' ? 443 : 80);

            continue;
        }

        if ($httpCode < 200 || $httpCode >= 400) {
            return null;
        }

        return $responseBody;
    }
}

/**
 * Ham sitemap XML'ini kök eleman tipine göre (urlset / sitemapindex)
 * ayrıştırır.
 *
 * @return array{type: string|null, urls: list<string>}
 */
function sitemapParseByType(?string $xml): array
{
    if ($xml === null || trim($xml) === '') {
        return ['type' => null, 'urls' => []];
    }

    $previous = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    libxml_use_internal_errors($previous);

    if ($doc === false) {
        return ['type' => null, 'urls' => []];
    }

    $rootName = $doc->getName();

    if ($rootName === 'sitemapindex') {
        $urls = [];
        foreach ($doc->sitemap ?? [] as $sitemap) {
            if (isset($sitemap->loc)) {
                $urls[] = trim((string) $sitemap->loc);
            }
        }
        return ['type' => 'sitemapindex', 'urls' => array_values(array_unique(array_filter($urls)))];
    }

    $urls = [];
    foreach ($doc->url ?? [] as $url) {
        if (isset($url->loc)) {
            $urls[] = trim((string) $url->loc);
        }
    }
    return ['type' => 'urlset', 'urls' => array_values(array_unique(array_filter($urls)))];
}

// --------------------------------------------------------------------------
// Ana akış
// --------------------------------------------------------------------------

const SITEMAP_MAX_CHILD_SITEMAPS = 10;
const SITEMAP_MAX_TOTAL_URLS     = 500;

$domain = trim((string) ($_GET['url'] ?? ''));

if ($domain === '') {
    http_response_code(400);
    echo json_encode(['error' => 'url parametresi gerekli.']);
    exit;
}

if (!preg_match('#^https?://#i', $domain)) {
    $domain = 'https://' . $domain;
}
$domain = rtrim($domain, '/');

// DÜZELTME: müşterinin kayıtlı domain_url'i bir alt yol içerebilir (ör.
// çok dilli bir site için "https://site.com/tr"), ama sitemap.xml ve
// robots.txt her zaman sitenin KÖK domain'inde bulunur, alt yolda değil.
// Bu yüzden fetch için yolu atıp sadece scheme+host(+port) kullanıyoruz.
$domainParts = parse_url($domain);
if ($domainParts === false || empty($domainParts['host'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz domain adresi.']);
    exit;
}
$rootOrigin = ($domainParts['scheme'] ?? 'https') . '://' . $domainParts['host']
    . (isset($domainParts['port']) ? ':' . $domainParts['port'] : '');

$sitemapXml = sitemapSafeFetch($rootOrigin . '/sitemap.xml');

// /sitemap.xml bulunamadıysa robots.txt'de bildirilen sitemap'i dene
if ($sitemapXml === null) {
    $robotsBody = sitemapSafeFetch($rootOrigin . '/robots.txt');
    if ($robotsBody !== null) {
        $checker = new OnPageIndexabilityChecker();
        $parsedRobots = $checker->parseRobotsTxt($robotsBody);
        $declaredSitemaps = $parsedRobots['sitemaps'] ?? [];
        if (!empty($declaredSitemaps)) {
            $sitemapXml = sitemapSafeFetch($declaredSitemaps[0]);
        }
    }
}

if ($sitemapXml === null) {
    http_response_code(502);
    echo json_encode(['error' => 'sitemap.xml bulunamadı veya erişilemedi.']);
    exit;
}

$parsed = sitemapParseByType($sitemapXml);

if ($parsed['type'] === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Geçersiz sitemap XML formatı.']);
    exit;
}

$allUrls = [];

if ($parsed['type'] === 'sitemapindex') {
    $childSitemaps = array_slice($parsed['urls'], 0, SITEMAP_MAX_CHILD_SITEMAPS);

    foreach ($childSitemaps as $childUrl) {
        $childXml = sitemapSafeFetch($childUrl);
        if ($childXml === null) {
            continue;
        }
        $childParsed = sitemapParseByType($childXml);
        // İç içe sitemap index'leri (index -> index) desteklenmiyor; sadece
        // gerçek <urlset> yaprakları toplanıyor (sonsuz döngüye girmemek için).
        if ($childParsed['type'] !== 'urlset') {
            continue;
        }
        foreach ($childParsed['urls'] as $u) {
            $allUrls[] = $u;
            if (count($allUrls) >= SITEMAP_MAX_TOTAL_URLS) {
                break 2;
            }
        }
    }
} else {
    $allUrls = array_slice($parsed['urls'], 0, SITEMAP_MAX_TOTAL_URLS);
}

$allUrls = array_values(array_unique($allUrls));

echo json_encode($allUrls);

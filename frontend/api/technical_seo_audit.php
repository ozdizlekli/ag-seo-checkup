<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    header('Content-Type: application/x-ndjson; charset=utf-8');

    echo json_encode([
        'type' => 'result',
        'success' => false,
        'error' => 'Bu işlem için giriş yapmanız gerekiyor.'
    ], JSON_UNESCAPED_UNICODE) . "\n";

    exit;
}

/**
 * Teknik SEO Denetim API'si — stateless (veritabanısız) uç nokta.
 *
 * frontend/js/technical-seo.js buraya {"url": "..."} POST eder, biz de
 * PageSpeed Insights + kendi taramamızı (robots.txt, sitemap.xml, SSL,
 * noindex, canonical, kırık linkler, site yapısı, mobil-öncelikli uyum,
 * schema.org) sunucu tarafında çalıştırıp ScoringEngine ile tek bir
 * ağırlıklı/kapılı (weighted + gated) skor ve önceliklendirilmiş bulgu
 * listesi üreterek JSON olarak döneriz.
 *
 * CANLI İLERLEME (STREAMING): Yanıt tek bir JSON nesnesi DEĞİL, satır satır
 * JSON'dur - her satır bağımsız bir olay. Her teknik kontrol adımı
 * başlarken {"type":"progress","step":"...","status":"start"}, biterken
 * aynı step için {"...","status":"done"} satırı gönderilir (flush() ile
 * hemen tarayıcıya iletilir) - böylece kullanıcı hangi kontrolün o an
 * çalıştığını gerçek zamanlı görür. En son satır HER ZAMAN
 * {"type":"result", ...eskiden tek parça dönen JSON'un tüm alanları...}
 * şeklindedir. Bu gerçek bir SSE (text/event-stream) değil - fetch() +
 * ReadableStream ile okunan, kendi basit satır-satır-JSON protokolümüz
 * (bkz. technical-seo.js: fetchStreamed()). Araya bir ters vekil sunucu
 * (ör. ileride nginx) girerse ve tamponlarsa canlı hissi kaybolur ama
 * son satır yine tam sonucu taşıdığı için işlev bozulmaz; nginx arkasına
 * alınırsa bu route için `proxy_buffering off;` (aşağıda gönderdiğimiz
 * `X-Accel-Buffering: no` header'ı bunu sağlar) tanımlanmalıdır.
 *
 * İKİ AŞAMALI TARAMA: Standart istekte ({"url":"..."}) site en fazla
 * $config['crawl']['max_pages'] sayfa ile taranır. Bu sınıra takılıp
 * kesilirse (site_structure.truncated=true) sonuç bir 'resume_state' ve
 * o anki PSI sonucunu da döner. İstemci kullanıcıya "sitenin tamamını
 * taramak ister misiniz?" diye sorup onay alırsa, AYNI uç noktaya
 * {"url":"...", "resume_state": <öncekinden>, "cached_psi": <öncekinden>}
 * gönderir - biz de tarama durumunu KALDIĞI YERDEN, çok daha yüksek
 * limitlerle (full_max_pages/full_max_depth/full_max_time_seconds)
 * devam ettiririz ve PSI'yi TEKRAR ÇAĞIRMAYIZ (cached_psi'yi aynen
 * kullanırız - PSI zaten sayfa sayısından bağımsız, tek seferlik bir
 * Lighthouse ölçümü). Sunucu durumsuz (stateless) kalmaya devam eder -
 * kaldığı-yer bilgisi istemci tarafında saklanıp geri gönderilir.
 *
 * gemini_proxy.php ile aynı desen: PSI API anahtarı SADECE burada, sunucu
 * tarafında .env'den okunur - tarayıcıya asla gönderilmez.
 */

use App\TechnicalSeo\Crawler;
use App\TechnicalSeo\LinkChecker;
use App\TechnicalSeo\OnPageContentChecker;
use App\TechnicalSeo\OnPageIndexabilityChecker;
use App\TechnicalSeo\PageSpeedClient;
use App\TechnicalSeo\SchemaValidator;
use App\TechnicalSeo\ScoringEngine;
use App\TechnicalSeo\SiteStructureAnalyzer;
use App\TechnicalSeo\SslChecker;

ini_set('display_errors', '0');
error_reporting(E_ALL);
set_time_limit(150);

// ---- Canlı ilerleme akışı (streaming) çıktı ayarları ----
// Halihazırda açık olan çıktı tamponlarını (varsa) kapatıp her echo'nun
// hemen tarayıcıya gitmesini sağlıyoruz - aksi halde flush() etkisiz kalır.
while (ob_get_level() > 0) {
    ob_end_flush();
}
ini_set('zlib.output_compression', '0');
ob_implicit_flush(true);

header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // ileride nginx arkasına alınırsa bu route'ta tamponlamayı kapatır

/** Tek bir olay satırı gönderir ve hemen tarayıcıya iter. */
function sendEvent(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

/**
 * Bir adımı 'start' olarak bildirir, $fn'i çalıştırır, sonra 'done' bildirir
 * ve $fn'in dönüş değerini geri verir (dönüş değeri kullanılmayan adımlarda
 * $fn genelde dış değişkenleri 'use (&$var)' ile referansla günceller).
 */
function runStep(string $step, string $label, callable $fn)
{
    sendEvent(['type' => 'progress', 'step' => $step, 'label' => $label, 'status' => 'start']);
    $result = $fn();
    sendEvent(['type' => 'progress', 'step' => $step, 'label' => $label, 'status' => 'done']);
    return $result;
}

/** Çalıştırılmayan (ör. önbellekten kullanılan) bir adımı 'skipped' olarak bildirir. */
function skipStep(string $step, string $label): void
{
    sendEvent(['type' => 'progress', 'step' => $step, 'label' => $label, 'status' => 'skipped']);
}

/** Deterministik sitemap çözümü; AI teknik kararlara dahil edilmez. */
function buildSitemapSolution(bool $found, string $xml, array $existingUrls, array $pages, array $missingUrls): array
{
    $pageByUrl = [];
    foreach ($pages as $page) $pageByUrl[rtrim((string) ($page['url'] ?? ''), '/')] = $page;
    $safe = []; $review = []; $excluded = [];
    foreach ($missingUrls as $url) {
        $page = $pageByUrl[rtrim((string) $url, '/')] ?? null;
        $reason = null;
        if (!is_array($page)) { $review[] = ['url' => $url, 'reason' => 'Tarama ayrıntıları doğrulanamadı.']; continue; }
        $status = (int) ($page['status_code'] ?? 0);
        $finalUrl = (string) ($page['final_url'] ?? $url);
        $canonical = $page['canonical'] ?? null;
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($status < 200 || $status >= 300) $reason = "HTTP {$status}";
        elseif ($finalUrl !== $url) $reason = 'Yönlendirme yapan URL';
        elseif (($page['noindex'] ?? null) === true) $reason = 'noindex';
        elseif (is_string($canonical) && rtrim($canonical, '/') !== rtrim($url, '/')) $reason = 'Canonical başka URL’ye işaret ediyor';
        elseif (($page['canonical_issue'] ?? null) !== null) $reason = 'Canonical yapısı belirsiz';
        elseif ($query !== '') { $review[] = ['url' => $url, 'reason' => 'Query/filter URL’si; manuel karar gerekli.']; continue; }
        elseif (preg_match('#/(admin|wp-admin|login|cart|checkout|account|search)(/|$)#i', $path)) $reason = 'Yönetim/sistem sayfası';
        if ($reason !== null) { $excluded[] = ['url' => $url, 'reason' => $reason]; continue; }
        $safe[] = $url;
    }

    $isIndex = $found && preg_match('/<\s*sitemapindex\b/i', $xml) === 1;
    $doc = new \DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $outputMode = $isIndex ? 'supplemental' : 'replacement';
    if (!$isIndex && $found && trim($xml) !== '' && @$doc->loadXML($xml, LIBXML_NONET)) {
        $root = $doc->documentElement;
    } else {
        $doc->appendChild($root = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset'));
        if (!$isIndex) foreach ($existingUrls as $url) { $node = $doc->createElement('url'); $node->appendChild($doc->createElement('loc'))->appendChild($doc->createTextNode($url)); $root->appendChild($node); }
    }
    $currentCount = count($existingUrls);
    $capacity = max(0, 50000 - ($isIndex ? 0 : $currentCount));
    $toAdd = array_slice($safe, 0, $capacity);
    foreach ($toAdd as $url) { $node = $doc->createElement('url'); $node->appendChild($doc->createElement('loc'))->appendChild($doc->createTextNode($url)); $root->appendChild($node); }
    if (count($safe) > count($toAdd)) $review[] = ['url' => '', 'reason' => '50.000 URL sınırı nedeniyle sitemap index/parçalama gerekli.'];
    $generated = $doc->saveXML() ?: '';
    $validator = new \DOMDocument();
    $valid = $generated !== '' && @$validator->loadXML($generated, LIBXML_NONET) && $validator->documentElement?->localName === 'urlset';
    return [
        'available' => $valid && $toAdd !== [], 'valid_xml' => $valid, 'mode' => $outputMode,
        'existing_count' => $currentCount, 'missing' => array_values($missingUrls),
        'safe_to_add' => $toAdd, 'excluded' => $excluded, 'review_required' => $review,
        'xml' => $valid ? $generated : null,
        'note' => $isIndex ? 'Mevcut sitemap index korunur; çıktı ayrı bir ek sitemap olarak sunulur ve indexe manuel eklenmelidir.' : 'Mevcut URL sırası korunarak güvenli adaylar sona eklendi.',
    ];
}

$config = require __DIR__ . '/../src/TechnicalSeo/Bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        sendEvent(['type' => 'result', 'success' => false, 'error' => 'Sadece POST istekleri desteklenir.']);
        exit;
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);
    $rawUrl = is_array($payload) ? trim((string) ($payload['url'] ?? '')) : '';

    if ($rawUrl === '') {
        sendEvent(['type' => 'result', 'success' => false, 'error' => 'Lütfen analiz edilecek bir URL girin.']);
        exit;
    }

    // "Tüm siteyi tara" (2. aşama) isteği mi? İstemci, 1. aşamada aldığı
    // resume_state'i aynen geri gönderirse taramayı kaldığı yerden, çok
    // daha yüksek limitlerle devam ettiririz.
    $resumeState = (is_array($payload) && isset($payload['resume_state']) && is_array($payload['resume_state']))
        ? $payload['resume_state']
        : null;
    $cachedPsi = (is_array($payload) && isset($payload['cached_psi']) && is_array($payload['cached_psi']))
        ? $payload['cached_psi']
        : null;
    $isFullCrawl = $resumeState !== null;

    // Tam taramada, önceki parçalardan kalan "hangi linkler zaten test
    // edildi + kümülatif kırık/sağlam sayısı" durumunu resume_state'in
    // içinden çıkarıyoruz (bkz. adım 9 - kırık link taraması artık parça
    // parça, TÜM linkleri kapsayacak şekilde çalışıyor).
    $priorLinkCheckState = (is_array($resumeState) && isset($resumeState['link_check']) && is_array($resumeState['link_check']))
        ? $resumeState['link_check']
        : ['checked' => [], 'broken' => [], 'ok_count' => 0];

    if ($isFullCrawl) {
        // Tam taramada (2000 sayfaya kadar) toplam süre standart moddan
        // belirgin şekilde uzun sürebilir - zaman aşımını buna göre uzat.
        set_time_limit(220);
    }

    if (!preg_match('#^https?://#i', $rawUrl)) {
        $rawUrl = 'https://' . $rawUrl;
    }

    if (!filter_var($rawUrl, FILTER_VALIDATE_URL)) {
        sendEvent(['type' => 'result', 'success' => false, 'error' => 'Girilen URL geçerli değil.']);
        exit;
    }

    $desktopUa = $config['http']['user_agent_desktop'];
    $mobileUa = $config['http']['user_agent_mobile'];
    $httpTimeout = $config['http']['timeout'];
    $maxRedirects = $config['http']['max_redirects'];

    $crawler = new Crawler($desktopUa, $httpTimeout, $maxRedirects);
    $indexChecker = new OnPageIndexabilityChecker();

    // 1) Ana sayfayı çek.
    $homepage = runStep('homepage', 'Ana sayfa çekiliyor', function () use ($crawler, $rawUrl) {
        return $crawler->fetch($rawUrl);
    });

    if ($homepage['error'] !== null) {
        sendEvent(['type' => 'result', 'success' => false, 'error' => 'Siteye ulaşılamadı: ' . $homepage['error']]);
        exit;
    }

    $finalUrl = $homepage['final_url'];
    $host = (string) parse_url($finalUrl, PHP_URL_HOST);
    $scheme = (string) parse_url($finalUrl, PHP_URL_SCHEME);
    $origin = $scheme . '://' . $host;

    // 2) SSL denetimi (sadece host'a bakar, https olsun olmasın deneriz).
    $sslChecker = new SslChecker(10);
    $ssl = runStep('ssl', 'SSL / HTTPS denetimi yapılıyor', function () use ($sslChecker, $host) {
        return $sslChecker->check($host);
    });

    // 3) robots.txt
    runStep('robots', 'robots.txt kontrol ediliyor', function () use ($crawler, $origin, $indexChecker, &$robotsFound, &$robotsParsed, &$robotsBlocksSite) {
        $robotsResult = $crawler->fetch($origin . '/robots.txt');
        $robotsFound = $robotsResult['error'] === null && $robotsResult['status_code'] === 200;
        $robotsParsed = $indexChecker->parseRobotsTxt($robotsFound ? $robotsResult['body'] : null);
        $robotsBlocksSite = $robotsFound && $indexChecker->blocksEntireSite($robotsParsed['groups']);
    });

    // 4) sitemap.xml (robots.txt içinde farklı bir sitemap belirtilmişse onu, yoksa varsayılan yolu dene)
    $sitemapXml = '';
    runStep('sitemap', 'sitemap.xml kontrol ediliyor', function () use ($crawler, $origin, $indexChecker, $robotsParsed, &$sitemapUrl, &$sitemapFound, &$sitemapUrls, &$sitemapXml) {
        $sitemapUrl = $robotsParsed['sitemaps'][0] ?? ($origin . '/sitemap.xml');
        $sitemapResult = $crawler->fetch($sitemapUrl);
        $sitemapFound = $sitemapResult['error'] === null && $sitemapResult['status_code'] === 200;
        $sitemapXml = $sitemapFound ? (string) $sitemapResult['body'] : '';
        $sitemapUrls = $sitemapFound ? $indexChecker->parseSitemapUrls($sitemapResult['body']) : [];
    });

    // 5) noindex + canonical (ana sayfa üzerinden)
    runStep('indexability', 'Noindex ve canonical etiketi kontrol ediliyor', function () use ($indexChecker, $homepage, $finalUrl, &$noindex, &$canonical) {
        $noindex = $indexChecker->checkNoindex($homepage['body'], $homepage['headers']);
        $canonical = $indexChecker->checkCanonical($homepage['body'], $finalUrl);
    });

    // 5b) JS'e bağımlılık ipucu (gerçek render YOK - bu proje headless
    // tarayıcı kullanmıyor; bkz. OnPageIndexabilityChecker::checkJsDependency
    // metodundaki not). Ham HTML neredeyse boşsa ve tipik bir SPA kök kutusu
    // (React/Vue/Angular) tespit edilirse, GPTBot/ClaudeBot/PerplexityBot gibi
    // JS çalıştırmayan botların sayfa içeriğini hiç görememe ihtimali işaretlenir.
    $jsDependency = $indexChecker->checkJsDependency($homepage['body']);

    // 7) Site yapısı taraması (dahili linkler, sayfa sayısı) - standart mod
    // 100 sayfa/derinlik 6 ile başlar; "tüm siteyi tara" isteğinde (resume_state
    // geldiyse) çok daha yüksek limitlerle kaldığı yerden devam eder.
    $crawlLabel = $isFullCrawl ? 'Sitenin tamamı taranıyor (bu biraz zaman alabilir)' : 'Site içi bağlantılar taranıyor';
    runStep('crawl', $crawlLabel, function () use ($crawler, $config, $isFullCrawl, $resumeState, $finalUrl, $indexChecker, &$siteStructureAnalyzer, &$siteStructure, &$crawledUrls) {
        if ($isFullCrawl) {
            $siteStructureAnalyzer = new SiteStructureAnalyzer(
                $crawler,
                (int) $config['crawl']['full_max_pages'],
                (int) $config['crawl']['full_max_depth'],
                (int) $config['crawl']['full_max_time_seconds'],
                (int) $config['crawl']['concurrency'],
                $indexChecker
            );
            $siteStructure = $siteStructureAnalyzer->crawl($finalUrl, $resumeState);
        } else {
            $siteStructureAnalyzer = new SiteStructureAnalyzer(
                $crawler,
                (int) $config['crawl']['max_pages'],
                (int) $config['crawl']['max_depth'],
                (int) $config['crawl']['max_time_seconds'],
                (int) $config['crawl']['concurrency'],
                $indexChecker
            );
            $siteStructure = $siteStructureAnalyzer->crawl($finalUrl);
        }
        $crawledUrls = array_column($siteStructure['pages'], 'url');
    });

    // 7b) Sayfa içeriği kalite kontrolleri: yinelenen title/meta description
    // ve H1-H6 başlık hiyerarşisi (eksik H1, birden fazla H1, seviye
    // atlaması). Ana veri yukarıdaki crawl() adımında zaten her sayfa için
    // çıkarılan title/meta_description/heading_structure alanlarından gelir
    // (bkz. SiteStructureAnalyzer::extractMetaDescription / extractHeadingStructure).
    // ASAMA 2 İSTİSNASI: yinelenen title/meta gruplarındaki canonical hedefi
    // crawl dışında kalmışsa (sayfa/derinlik limiti), OnPageContentChecker
    // bu hedefi CANLI olarak doğrulamak için ek (sınırlı sayıda) HTTP isteği
    // atabilir - bkz. OnPageContentChecker::fetchLiveTarget().
    $contentQuality = runStep('content_quality', 'Sayfa içeriği kontrol ediliyor (yinelenen title/meta, başlık hiyerarşisi)', function () use ($siteStructure, $crawler) {
        // Asama 2: Crawler veriliyor ki, yinelenen title/meta gruplarindaki
        // canonical hedefleri crawl disinda kalmissa (sayfa/derinlik limiti)
        // canli olarak dogrulanabilsin (bkz. OnPageContentChecker::fetchLiveTarget()).
        $contentChecker = new OnPageContentChecker($crawler);
        return [
            'duplicate_titles' => $contentChecker->findDuplicateTitles($siteStructure['pages']),
            'duplicate_meta_descriptions' => $contentChecker->findDuplicateMetaDescriptions($siteStructure['pages']),
            'heading_hierarchy' => $contentChecker->analyzeHeadingHierarchy($siteStructure['pages']),
            'canonical_structural_issues' => $contentChecker->findCanonicalIssues($siteStructure['pages']),
        ];
    });

    // 8) Sitemap <-> taranan sayfalar çapraz kontrolü (orphan page tespiti)
    runStep('orphan', 'Sitemap ile çapraz kontrol ediliyor', function () use ($indexChecker, $crawledUrls, $sitemapUrls, &$crossRef, &$orphanRatio) {
        $crossRef = $indexChecker->crossReferenceSitemap($crawledUrls, $sitemapUrls);
        $orphanRatio = count($crawledUrls) > 0 ? round((count($crossRef['orphan_pages']) / count($crawledUrls)) * 100, 1) : 0.0;
    });

    // 9) Kırık link taraması (taranan dahili linkler üzerinden)
    // Standart modda hâlâ tek seferde en fazla 40 link test edilir. Tam site
    // taramasında ("Tüm Siteyi Tara") ise artık TÜM linkler kapsanıyor - ama
    // tek bir istekte binlerce linki aynı anda test edip zaman aşımına
    // uğramamak için, her parçada SADECE o parçada yeni bulunan ve daha önce
    // test edilmemiş linkleri (en fazla full_max_links_to_check kadarını)
    // test ediyoruz. Kümülatif durum (hangi linkler test edildi, kaçı kırık)
    // resume_state üzerinden bir sonraki parçaya taşınır - tarama tamamen
    // bitince (site yapısı VE link kontrolü ikisi de tamamlanınca) TÜM
    // linkler test edilmiş olur.
    $linkCheckState = null;
    runStep('links', 'Kırık linkler taranıyor', function () use (
        $crawler, $config, $siteStructure, $isFullCrawl, $priorLinkCheckState, &$linkCheckResult, &$linkCheckState
    ) {
        if (!$isFullCrawl) {
            $linkChecker = new LinkChecker($crawler, (int) $config['crawl']['max_links_to_check'], (int) $config['crawl']['link_check_concurrency']);
            $linkCheckResult = $linkChecker->check($siteStructure['internal_links_found']);
            return;
        }

        $alreadyChecked = $priorLinkCheckState['checked'];
        $newLinks = array_values(array_filter(
            $siteStructure['internal_links_found'],
            static fn (string $url): bool => !isset($alreadyChecked[$url])
        ));

        $linkChecker = new LinkChecker($crawler, (int) $config['crawl']['full_max_links_to_check'], (int) $config['crawl']['link_check_concurrency']);
        $batch = $linkChecker->check($newLinks);

        foreach (array_slice($newLinks, 0, $batch['checked_count']) as $url) {
            $alreadyChecked[$url] = true;
        }

        $mergedBroken = array_merge($priorLinkCheckState['broken'], $batch['broken']);
        $mergedOkCount = $priorLinkCheckState['ok_count'] + $batch['ok_count'];
        $stillPending = count($newLinks) - $batch['checked_count'];

        $linkCheckResult = [
            'checked_count' => count($alreadyChecked),
            'total_links_found' => count($siteStructure['internal_links_found']),
            'broken' => $mergedBroken,
            'ok_count' => $mergedOkCount,
            // Artık "hâlâ test edilmeyi bekleyen yeni link var mı" anlamına
            // geliyor - tüm linkler test edilene kadar true kalabilir.
            'truncated' => $stillPending > 0,
        ];

        $linkCheckState = [
            'checked' => $alreadyChecked,
            'broken' => $mergedBroken,
            'ok_count' => $mergedOkCount,
        ];
    });

    // 10) Mobil-öncelikli indeksleme uyumu (masaüstü UA vs gerçek Googlebot mobil UA)
    $mobileParity = runStep('mobile', 'Mobil-öncelikli uyum karşılaştırılıyor', function () use ($siteStructureAnalyzer, $finalUrl, $desktopUa, $mobileUa) {
        return $siteStructureAnalyzer->checkMobileFirstParity($finalUrl, $desktopUa, $mobileUa);
    });

    // 11) Schema.org / JSON-LD doğrulanıyor
    runStep('schema', 'Schema.org / JSON-LD doğrulanıyor', function () use ($homepage, &$jsonLdBlocks, &$schemaIssues) {
        $schemaValidator = new SchemaValidator();
        $jsonLdBlocks = $schemaValidator->extractJsonLd($homepage['body']);
        $schemaIssues = $schemaValidator->validate($jsonLdBlocks);
    });

    // 12) PageSpeed Insights (mobil + masaüstü paralel) - "tüm siteyi tara"
    // isteğinde PSI'yi TEKRAR ÇAĞIRMIYORUZ: Lighthouse ölçümü taranan sayfa
    // sayısından bağımsızdır, 1. aşamadan istemcinin geri gönderdiği sonucu
    // aynen kullanmak hem gereksiz API kotası harcamayı hem de gecikmeyi önler.
    if ($isFullCrawl && $cachedPsi !== null) {
        $psi = $cachedPsi;
        skipStep('psi', 'PageSpeed Insights (önceki taramadan kullanılıyor)');
    } else {
        $psi = runStep('psi', 'PageSpeed Insights (Lighthouse) ölçülüyor', function () use ($config, $finalUrl) {
            $psiClient = new PageSpeedClient($config['psi']['api_key'], $config['psi']['endpoint'], (int) $config['psi']['timeout']);
            return $psiClient->analyzeBoth($finalUrl);
        });
    }

    // 13) ---- Hepsini ScoringEngine'e besle ----
    $scoreResult = runStep('scoring', 'Nihai skor hesaplanıyor', function () use (
        $siteStructure, $homepage, $noindex, $robotsBlocksSite, $robotsFound, $sitemapFound,
        $sitemapUrls, $canonical, $orphanRatio, $crossRef, $psi,
        $linkCheckResult, $ssl, $schemaIssues, $mobileParity, $jsDependency, $contentQuality
    ) {
        $scoringInput = [
            'crawled_page_count' => max(1, count($siteStructure['pages'])),
            'homepage_status_code' => $homepage['status_code'],
            'indexability' => [
                'noindex' => $noindex,
                'robots_blocks_site' => $robotsBlocksSite,
                'robots_txt_found' => $robotsFound,
                'sitemap_found' => $sitemapFound,
                'sitemap_url_count' => count($sitemapUrls),
                'canonical' => $canonical,
                'orphan_page_ratio_percent' => $orphanRatio,
                'orphan_pages' => $crossRef['orphan_pages'],
                'sitemap_only_pages' => $crossRef['sitemap_only'],
                'js_dependency' => $jsDependency,
                'duplicate_titles' => $contentQuality['duplicate_titles'],
                'duplicate_meta_descriptions' => $contentQuality['duplicate_meta_descriptions'],
                'heading_hierarchy' => $contentQuality['heading_hierarchy'],
                'canonical_structural_issues' => $contentQuality['canonical_structural_issues'],
            ],
            'psi' => $psi,
            'link_check' => $linkCheckResult,
            'site_structure' => $siteStructure,
            'ssl' => $ssl,
            'schema_issues' => $schemaIssues,
            'mobile_parity' => $mobileParity,
        ];

        $scoringEngine = new ScoringEngine();
        return $scoringEngine->compute($scoringInput);
    });

    // Site yapısı taraması bitmiş olsa bile (truncated=false), hâlâ test
    // edilmeyi bekleyen YENİ linkler varsa devam etme imkanını açık
    // tutuyoruz - yoksa "site tamamen tarandı" deyip son birkaç yüz linki
    // hiç test etmeden bırakmış oluruz. Bu durumda site yapısı tarafında
    // yapılacak gerçek bir iş kalmadığı için (queue boş) bir sonraki çağrı
    // sadece link kontrolüne devam eder, sayfa taramasına dokunmaz.
    $responseResumeState = $siteStructure['resume_state'] ?? null;
    $linkCheckPending = $isFullCrawl && ($linkCheckResult['truncated'] ?? false);

    if ($linkCheckPending && $responseResumeState === null) {
        $responseResumeState = [
            'visited' => [],
            'queue' => [],
            'pages' => $siteStructure['pages'],
            'internal_links_found' => $siteStructure['internal_links_found'],
        ];
    }

    if ($isFullCrawl && $responseResumeState !== null && $linkCheckState !== null) {
        $responseResumeState['link_check'] = $linkCheckState;
    }

    $sitemapSolution = buildSitemapSolution($sitemapFound, $sitemapXml, $sitemapUrls, $siteStructure['pages'], $crossRef['orphan_pages']);

    sendEvent([
        'type' => 'result',
        'success' => true,
        'url' => $finalUrl,
        'redirect_chain' => $homepage['redirect_chain'],
        'score' => $scoreResult,
        'psi' => $psi,
        'quick_audit' => [
            'ssl' => $ssl,
            'robots_txt' => ['found' => $robotsFound, 'blocks_entire_site' => $robotsBlocksSite, 'sitemaps_declared' => $robotsParsed['sitemaps']],
            'sitemap' => ['found' => $sitemapFound, 'url_count' => count($sitemapUrls), 'checked_url' => $sitemapUrl],
            'noindex' => $noindex,
            'canonical' => $canonical,
        ],
        'site_structure' => [
            'crawl_mode' => $isFullCrawl ? 'full' : 'standard',
            'crawled_page_count' => count($siteStructure['pages']),
            'truncated' => $siteStructure['truncated'],
            'truncated_reason' => $siteStructure['truncated_reason'] ?? null,
            'orphan_pages' => $crossRef['orphan_pages'],
            'sitemap_only_pages' => $crossRef['sitemap_only'],
            // Site yapısı kesildiyse YA DA link kontrolü hâlâ bekliyorsa dolu
            // gelir - istemci bunu ve 'psi'yi saklayıp bir sonraki istekte
            // aynen geri gönderir (bkz. runFullSiteCrawl).
            'resume_state' => $responseResumeState,
        ],
        'link_check' => $linkCheckResult,
        'mobile_parity' => $mobileParity,
        'schema' => ['detected_json_ld_count' => count($jsonLdBlocks), 'issues' => $schemaIssues],
        'solutions' => ['sitemap' => $sitemapSolution],
    ]);
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    sendEvent([
        'type' => 'result',
        'success' => false,
        'error' => 'Beklenmeyen bir sunucu hatası oluştu: ' . $e->getMessage(),
    ]);
}

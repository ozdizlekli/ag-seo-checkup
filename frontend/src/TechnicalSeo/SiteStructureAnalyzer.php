<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * Saf PHP (DOMDocument/DOMXPath) ile site içi bağlantı yapısını çıkarır.
 *
 * Katman-katman (BFS) paralel tarama: her turda kuyruktan bir grup (chunk)
 * URL alınır ve Crawler::fetchMultiple() ile eşzamanlı (curl_multi) çekilir -
 * bu, art arda tek tek fetch() çağırmaktan çok daha hızlıdır. Kuyruk derinliğe
 * göre sıralı büyüdüğü için ardışık gruplar doğal olarak aynı derinlikteki
 * sayfalara denk gelir.
 *
 * Üç güvenlik/verimlilik sınırı vardır (hangisi önce dolarsa tarama durur):
 * maxPages (sayfa sayısı), maxDepth (bağlantı derinliği), maxTimeSeconds
 * (duvar-saati süresi). Ayrıca izleme/oturum sorgu parametreleri (utm_*,
 * fbclid, PHPSESSID vb.) hem çekim hem de "ziyaret edildi" anahtarından ÖNCE
 * temizlenir - aynı sayfanın farklı izleme parametreli linkleri gereksiz
 * yere tekrar taranmaz.
 *
 * Devam ettirilebilir (resumable): crawl() taranmamış kuyruk + ziyaret
 * durumu ile kesildiyse bir 'resume_state' döner; bu state ikinci bir
 * crawl() çağrısına (farklı/daha yüksek limitlerle - ör. "tüm siteyi tara")
 * geri verilirse tarama kaldığı yerden devam eder.
 *
 * DÜRÜST SINIRLAMA: Bu bir headless tarayıcı değil - JavaScript render
 * ETMEZ. Yani sadece <a href> ile sunucu tarafından üretilmiş HTML'de yer
 * alan linkleri görür. Ağır JS-tabanlı (SPA) sitelerde link grafiği eksik
 * çıkabilir. Screaming Frog/Sitebulb gibi masaüstü araçların "JS render"
 * modu vardır, biz tarayıcı motoru başlatamadığımız (pure PHP) için bu
 * modu sunamıyoruz - bunu kullanıcıya raporda açıkça belirtiyoruz.
 */
final class SiteStructureAnalyzer
{
    private Crawler $crawler;
    private int $maxPages;
    private int $maxDepth;
    private int $maxTimeSeconds;
    private int $concurrency;

    /**
     * Sayı/harf değiştirse de sayfa içeriğini DEĞİŞTİRMEYEN, yaygın izleme/
     * oturum parametreleri - bunları farklı iki URL'i "aynı sayfa" saymak
     * için silmek güvenlidir. Belirsiz olabilecek (içeriği gerçekten
     * değiştirebilecek, ör. "ref", "page", "id") parametrelere ELLE
     * DOKUNMUYORUZ.
     *
     * @var list<string>
     */
    private const TRACKING_PARAM_NAMES = [
        'fbclid', 'gclid', 'gclsrc', 'dclid', 'msclkid', 'yclid', 'ttclid', 'twclid',
        'mc_cid', 'mc_eid', 'igshid', '_ga', '_gl',
        'phpsessid', 'jsessionid', 'sid', 'sessionid', 'aspsessionid',
    ];

    public function __construct(Crawler $crawler, int $maxPages = 100, int $maxDepth = 6, int $maxTimeSeconds = 60, int $concurrency = 10)
    {
        $this->crawler = $crawler;
        $this->maxPages = max(1, $maxPages);
        $this->maxDepth = max(0, $maxDepth);
        $this->maxTimeSeconds = max(1, $maxTimeSeconds);
        $this->concurrency = max(1, $concurrency);
    }

    /**
     * Genişlik-öncelikli (breadth-first), katman-katman PARALEL site içi
     * tarama. $resumeState verilirse (crawl()'un daha önce döndürdüğü
     * 'resume_state' alanı) kaldığı yerden devam eder - $startUrl bu
     * durumda yalnızca host tespiti için kullanılır, kuyruk/ziyaret durumu
     * $resumeState'ten gelir.
     *
     * @param array{visited:array<string,bool>, queue:list<array{url:string,depth:int}>, pages:list<array{url:string,status_code:int,internal_link_count:int,title:string|null}>, internal_links_found:list<string>}|null $resumeState
     * @return array{
     *   pages: list<array{url:string, status_code:int, internal_link_count:int, title:string|null}>,
     *   internal_links_found: list<string>,
     *   truncated: bool,
     *   truncated_reason: string|null,
     *   resume_state?: array{visited:array<string,bool>, queue:list<array{url:string,depth:int}>, pages:list<array{url:string,status_code:int,internal_link_count:int,title:string|null}>, internal_links_found:list<string>}
     * }
     */
    public function crawl(string $startUrl, ?array $resumeState = null): array
    {
        $start = microtime(true);
        $host = parse_url($startUrl, PHP_URL_HOST);

        if ($resumeState !== null) {
            $visited = $resumeState['visited'];
            $queue = $resumeState['queue'];
            $pages = $resumeState['pages'];
            $allInternalLinks = $resumeState['internal_links_found'];
        } else {
            $visited = [];
            $queue = [['url' => $this->stripTrackingParams($startUrl), 'depth' => 0]];
            $pages = [];
            $allInternalLinks = [];
        }

        $truncatedReason = null;

        while (!empty($queue)) {
            if (count($visited) >= $this->maxPages) {
                $truncatedReason = 'max_pages';
                break;
            }
            if ((microtime(true) - $start) >= $this->maxTimeSeconds) {
                $truncatedReason = 'max_time';
                break;
            }

            // Bu turda çekilecek grubu (chunk) oluştur: eşzamanlılık sınırı VE
            // kalan sayfa bütçesi ile sınırlı. Kuyruk derinliğe göre sıralı
            // büyüdüğünden ardışık öğeler pratikte hep aynı derinliktedir.
            $budget = min($this->concurrency, $this->maxPages - count($visited));
            $batch = [];
            while (!empty($queue) && count($batch) < $budget) {
                $item = array_shift($queue);
                $key = rtrim($item['url'], '/');
                if (isset($visited[$key])) {
                    continue; // başka bir sayfadan da linklenmiş, zaten ziyaret edildi/edilecek
                }
                $visited[$key] = true; // hemen işaretle - aynı chunk içinde tekrar eklenmesin
                $batch[$item['url']] = $item;
            }

            if (empty($batch)) {
                continue; // bu turdaki her şey zaten ziyaret edilmişti, sıradaki gruba geç
            }

            $urls = array_keys($batch);
            $results = $this->crawler->fetchMultiple($urls, $this->concurrency, false, true);

            foreach ($batch as $url => $item) {
                $result = $results[$url] ?? ['status_code' => 0, 'error' => 'Bilinmeyen hata.', 'final_url' => $url];

                if ($result['error'] !== null || $result['status_code'] >= 400) {
                    $pages[] = [
                        'url' => $url,
                        'status_code' => $result['status_code'],
                        'internal_link_count' => 0,
                        'title' => null,
                    ];
                    continue;
                }

                $body = $result['body'] ?? '';
                $effectiveUrl = $result['final_url'] ?? $url;
                $links = $this->extractInternalLinks($body, $effectiveUrl, $host);
                $title = $this->extractTitle($body);

                $pages[] = [
                    'url' => $url,
                    'status_code' => $result['status_code'],
                    'internal_link_count' => count($links),
                    'title' => $title,
                ];

                foreach ($links as $link) {
                    // Kırık link taraması ham (izleme parametreli) URL'i görsün diye
                    // orijinalini saklıyoruz - sadece kuyruğa eklerken temizliyoruz.
                    $allInternalLinks[] = $link;
                }

                if ($item['depth'] >= $this->maxDepth) {
                    continue; // derinlik sınırına ulaşıldı - bu sayfadan öteye kuyruğa eklenmez
                }

                foreach ($links as $link) {
                    $strippedLink = $this->stripTrackingParams($link);
                    $normalizedLink = rtrim($strippedLink, '/');
                    if (!isset($visited[$normalizedLink])) {
                        $queue[] = ['url' => $strippedLink, 'depth' => $item['depth'] + 1];
                    }
                }
            }
        }

        if ($truncatedReason === null && !empty($queue)) {
            $truncatedReason = 'max_pages'; // savunma amaçlı - normal şartlarda üstteki break'lerden biri yakalar
        }

        $truncated = $truncatedReason !== null;

        $result = [
            'pages' => $pages,
            'internal_links_found' => array_values(array_unique($allInternalLinks)),
            'truncated' => $truncated,
            'truncated_reason' => $truncatedReason,
        ];

        if ($truncated) {
            $result['resume_state'] = [
                'visited' => $visited,
                'queue' => array_values($queue),
                'pages' => $pages,
                'internal_links_found' => $allInternalLinks,
            ];
        }

        return $result;
    }

    /**
     * Sayfa içeriğini değiştirmeyen izleme/oturum sorgu parametrelerini
     * (utm_*, fbclid, PHPSESSID vb.) URL'den siler - hem gerçek çekim hem
     * de "ziyaret edildi" anahtarı için kullanılır, böylece aynı sayfanın
     * farklı izleme parametreli varyantları tekrar tekrar taranmaz.
     */
    private function stripTrackingParams(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['query']) || $parts['query'] === '') {
            return $url;
        }

        parse_str($parts['query'], $params);
        $changed = false;
        foreach (array_keys($params) as $key) {
            $lower = strtolower($key);
            if (str_starts_with($lower, 'utm_') || in_array($lower, self::TRACKING_PARAM_NAMES, true)) {
                unset($params[$key]);
                $changed = true;
            }
        }

        if (!$changed) {
            return $url;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = empty($params) ? '' : '?' . http_build_query($params);
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    /**
     * Aynı sayfayı iki farklı User-Agent (masaüstü / gerçek Googlebot mobil)
     * ile çekip temel içerik göstergelerini (kelime sayısı, başlık sayısı,
     * yapılandırılmış veri varlığı) karşılaştırır. Mobil-öncelikli indeksleme
     * gerçeği: Google artık sitenizi ÖNCELİKLE mobil sürümünüz üzerinden
     * değerlendiriyor - mobilde eksik içerik = indekste eksik içerik.
     */
    public function checkMobileFirstParity(string $url, string $desktopUa, string $mobileUa): array
    {
        $dual = $this->crawler->fetchDualUserAgent($url, $desktopUa, $mobileUa);

        $desktopBody = $dual['desktop']['body'] ?? '';
        $mobileBody = $dual['mobile']['body'] ?? '';

        if ($dual['desktop']['error'] !== null || $dual['mobile']['error'] !== null) {
            return [
                'comparable' => false,
                'error' => $dual['desktop']['error'] ?? $dual['mobile']['error'],
            ];
        }

        $desktopWordCount = $this->countWords($desktopBody);
        $mobileWordCount = $this->countWords($mobileBody);
        $desktopHeadings = $this->countHeadings($desktopBody);
        $mobileHeadings = $this->countHeadings($mobileBody);
        $desktopHasJsonLd = str_contains($desktopBody, 'application/ld+json');
        $mobileHasJsonLd = str_contains($mobileBody, 'application/ld+json');

        $wordCountDropPercent = $desktopWordCount > 0
            ? round((($desktopWordCount - $mobileWordCount) / $desktopWordCount) * 100, 1)
            : 0.0;

        return [
            'comparable' => true,
            'desktop_word_count' => $desktopWordCount,
            'mobile_word_count' => $mobileWordCount,
            'word_count_drop_percent' => $wordCountDropPercent,
            'desktop_heading_count' => $desktopHeadings,
            'mobile_heading_count' => $mobileHeadings,
            'structured_data_lost_on_mobile' => $desktopHasJsonLd && !$mobileHasJsonLd,
            // %20'den fazla kelime kaybı = anlamlı içerik farkı (ampirik eşik,
            // Google'ın resmi bir sayısı yok - "önemli ölçüde farklı" ifadesini kullanıyor).
            'significant_content_loss' => $wordCountDropPercent > 20,
            'note' => 'Bu karşılaştırma JavaScript render etmez - yalnızca sunucunun ham HTML çıktısındaki farkları yakalar.',
        ];
    }

    /**
     * @return list<string>
     */
    private function extractInternalLinks(string $html, string $baseUrl, ?string $host): array
    {
        if ($host === null || trim($html) === '') {
            return [];
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//a[@href]');
        $links = [];

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $href = trim($node->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $resolved = $this->resolveUrl($baseUrl, $href);
            $resolvedHost = parse_url($resolved, PHP_URL_HOST);

            if ($resolvedHost === $host) {
                $links[] = strtok($resolved, '#'); // fragment'i at
            }
        }

        return array_values(array_unique($links));
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(html_entity_decode($m[1]));
        }
        return null;
    }

    private function countWords(string $html): int
    {
        $text = $this->stripToText($html);
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        return count(array_filter($words, static fn ($w) => $w !== ''));
    }

    private function countHeadings(string $html): int
    {
        return (int) preg_match_all('/<h[1-6][ >]/i', $html);
    }

    private function stripToText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html) ?? $html;
        $text = strip_tags($html);
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function resolveUrl(string $baseUrl, string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }

        $basePath = $base['path'] ?? '/';
        $dir = rtrim(substr($basePath, 0, (int) strrpos($basePath, '/')), '/');
        return $scheme . '://' . $host . $dir . '/' . $href;
    }
}

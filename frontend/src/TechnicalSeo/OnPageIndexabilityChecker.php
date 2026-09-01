<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * "İndekslenmeyen sayfa her şey mükemmel olsa bile değersizdir" prensibiyle
 * yazılan denetleyici. Kızın eski aracındaki gibi "robots.txt var mı" ya da
 * "Disallow: / var mı" düz metin arasa bakmak yerine, gerçek robots.txt
 * kurallarını path bazında uygular (Allow/Disallow önceliği, en uzun eşleşen
 * kural kazanır - Google'ın kendi belgelediği davranış).
 */
final class OnPageIndexabilityChecker
{
    /**
     * robots.txt içeriğini ayrıştırır ve verilen user-agent grubuna ait
     * kuralları çıkarır.
     *
     * @return array{
     *   groups: array<string, list<array{type:string, path:string}>>,
     *   sitemaps: list<string>,
     *   raw_found: bool
     * }
     */
    public function parseRobotsTxt(?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return ['groups' => [], 'sitemaps' => [], 'raw_found' => false];
        }

        $groups = [];
        $sitemaps = [];
        $currentAgents = [];
        $lines = preg_split('/\r\n|\n|\r/', $body) ?: [];

        foreach ($lines as $line) {
            $line = preg_replace('/#.*$/', '', $line) ?? '';
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = explode(':', $line, 2);
            $field = strtolower(trim($field));
            $value = trim($value);

            if ($field === 'user-agent') {
                // Ardışık "User-agent:" satırları aynı kural grubuna aittir.
                if (!empty($currentAgents) && isset($groups[$currentAgents[0]]) && count($groups[$currentAgents[0]]) > 0) {
                    $currentAgents = [];
                }
                $currentAgents[] = strtolower($value);
                foreach ($currentAgents as $agent) {
                    $groups[$agent] ??= [];
                }
                continue;
            }

            if ($field === 'sitemap') {
                if ($value !== '') {
                    $sitemaps[] = $value;
                }
                continue;
            }

            if (in_array($field, ['disallow', 'allow'], true) && !empty($currentAgents)) {
                foreach ($currentAgents as $agent) {
                    $groups[$agent][] = ['type' => $field, 'path' => $value];
                }
            }
        }

        return ['groups' => $groups, 'sitemaps' => $sitemaps, 'raw_found' => true];
    }

    /**
     * Belirli bir path'in, robots.txt kurallarına göre verilen user-agent
     * için izinli olup olmadığını hesaplar. Google'ın belgelediği kurala göre:
     * en spesifik (en uzun) eşleşen kural kazanır; eşitlikte Allow kazanır.
     *
     * @param array<string, list<array{type:string, path:string}>> $groups
     */
    public function isPathAllowed(array $groups, string $userAgentToken, string $path): bool
    {
        $rules = $groups[strtolower($userAgentToken)] ?? $groups['*'] ?? [];

        if (empty($rules)) {
            return true; // Kural yoksa varsayılan: izinli.
        }

        $bestMatch = null;
        $bestLength = -1;

        foreach ($rules as $rule) {
            $pattern = $rule['path'];
            if ($pattern === '') {
                // "Disallow:" (boş) => hiçbir şeyi engellemez.
                if ($rule['type'] === 'disallow') {
                    continue;
                }
            }

            if ($this->robotsPatternMatches($pattern, $path)) {
                $length = strlen($pattern);
                if ($length > $bestLength) {
                    $bestLength = $length;
                    $bestMatch = $rule['type'];
                }
            }
        }

        if ($bestMatch === null) {
            return true;
        }

        return $bestMatch === 'allow';
    }

    /**
     * robots.txt path eşleştirmesi: '*' herhangi bir karakter dizisiyle,
     * '$' satır sonuyla eşleşir (Google'ın desteklediği genişletilmiş sözdizimi).
     */
    private function robotsPatternMatches(string $pattern, string $path): bool
    {
        if ($pattern === '/') {
            return true;
        }

        $endsWithDollar = str_ends_with($pattern, '$');
        $corePattern = $endsWithDollar ? substr($pattern, 0, -1) : $pattern;

        $regexParts = explode('*', $corePattern);
        $regexParts = array_map(static fn (string $p): string => preg_quote($p, '#'), $regexParts);
        $regex = '#^' . implode('.*', $regexParts) . ($endsWithDollar ? '$' : '') . '#';

        return (bool) preg_match($regex, $path);
    }

    /**
     * Sitenin genelini (Disallow: /) engelleyip engellemediğini kontrol eder
     * - "kritik kapı" (hard gate) tetikleyicilerinden biri.
     *
     * @param array<string, list<array{type:string, path:string}>> $groups
     */
    public function blocksEntireSite(array $groups, string $userAgentToken = '*'): bool
    {
        return !$this->isPathAllowed($groups, $userAgentToken, '/');
    }

    /**
     * HTML meta robots etiketinde ve X-Robots-Tag HTTP header'ında noindex
     * arar. Sadece birine bakmak yeterli değil: bir sunucu HTML'de noindex
     * göstermeyip header'da gösterebilir (ya da tam tersi).
     *
     * @param array<string,string> $headers küçük harfli header adları
     */
    public function checkNoindex(string $html, array $headers): array
    {
        $metaNoindex = false;
        $headerNoindex = false;
        $metaContent = null;

        if (preg_match(
            '/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i',
            $html,
            $m
        )) {
            $metaContent = $m[1];
            $metaNoindex = stripos($m[1], 'noindex') !== false;
        }

        $xRobotsTag = $headers['x-robots-tag'] ?? null;
        if ($xRobotsTag !== null) {
            $headerNoindex = stripos($xRobotsTag, 'noindex') !== false;
        }

        return [
            'noindex' => $metaNoindex || $headerNoindex,
            'source' => $metaNoindex && $headerNoindex ? 'both' : ($metaNoindex ? 'meta_tag' : ($headerNoindex ? 'x_robots_tag_header' : null)),
            'meta_robots_content' => $metaContent,
            'x_robots_tag' => $xRobotsTag,
        ];
    }

    /**
     * Canonical etiketinin kendine mi yoksa başka bir sayfaya mı işaret
     * ettiğini kontrol eder.
     */
    public function checkCanonical(string $html, string $pageUrl): array
    {
        if (!preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            return ['present' => false, 'canonical_url' => null, 'is_self_referencing' => null];
        }

        $canonicalUrl = trim($m[1]);
        $normalize = static fn (string $u): string => rtrim(strtolower(preg_replace('#^https?://#', '', $u) ?? $u), '/');

        return [
            'present' => true,
            'canonical_url' => $canonicalUrl,
            'is_self_referencing' => $normalize($canonicalUrl) === $normalize($pageUrl),
        ];
    }

    /**
     * Ham HTML'in (JS çalışmadan önceki hali) gerçek anlamda içerik taşıyıp
     * taşımadığına dair bir ipucu/heuristik verir - GERÇEK bir tarayıcı ile
     * render etmez (bkz. sınıf notları: bu proje headless tarayıcı kullanmaz).
     * Amaç: Googlebot JS render eder ama GPTBot, ClaudeBot, PerplexityBot gibi
     * birçok AI/arama botu HTML'i olduğu gibi okur, JS çalıştırmaz. Eğer body
     * içeriği neredeyse boşsa ve tipik bir SPA kök elemanından (React/Vue/
     * Angular'ın <div id="root">/<div id="app"> gibi boş kutusu) ibaretse,
     * bu botlar sayfanın gerçek içeriğini hiç göremiyor olabilir.
     *
     * @return array{likely_js_dependent:bool, visible_text_length:int, root_div_only:bool}
     */
    public function checkJsDependency(string $html): array
    {
        if (trim($html) === '') {
            return ['likely_js_dependent' => false, 'visible_text_length' => 0, 'root_div_only' => false];
        }

        $bodyHtml = $html;
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $bodyHtml = $m[1];
        }

        // <script>/<style> bloklarını tamamen çıkar - yoksa içindeki metin
        // (JS kodu, JSON verisi) görünür içerikmiş gibi sayılır.
        $stripped = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $bodyHtml) ?? $bodyHtml;
        $stripped = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/<!--.*?-->/s', '', $stripped) ?? $stripped;

        $textOnly = trim(preg_replace('/\s+/', ' ', strip_tags($stripped)) ?? '');
        $visibleTextLength = mb_strlen($textOnly);

        // Tipik SPA kök kutusu: id/class "root", "app", "__next", "___gatsby"
        // gibi bir div, içi (neredeyse) boş.
        $rootDivOnly = (bool) preg_match(
            "/<div[^>]+(?:id|class)=[\"'](root|app|__next|___gatsby|app-root)[\"'][^>]*>\s*<\/div>/i",
            $stripped
        );

        return [
            'likely_js_dependent' => $rootDivOnly || $visibleTextLength < 60,
            'visible_text_length' => $visibleTextLength,
            'root_div_only' => $rootDivOnly,
        ];
    }

    /**
     * sitemap.xml içeriğini ayrıştırır, <loc> URL'lerini döner. Sitemap index
     * dosyalarını (birden fazla sitemap'e işaret eden) da destekler - tek
     * seviye derinlikte, sonsuz döngüye girmemek için.
     *
     * @return list<string>
     */
    public function parseSitemapUrls(?string $xml, ?Crawler $crawler = null, int $maxSubSitemaps = 20): array
    {
        if ($xml === null || trim($xml) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            return [];
        }

        $urls = [];
        foreach ($doc->url ?? [] as $url) {
            if (isset($url->loc)) {
                $urls[] = trim((string) $url->loc);
            }
        }

        // <sitemapindex><sitemap><loc> girdileri GERCEK bir sayfa DEGIL, baska
        // bir sitemap dosyasina isaret ediyor (WordPress/Yoast/RankMath'te cok
        // yaygin bir yapi). Bunlari sanki normal bir sayfaymis gibi listeye
        // eklemek yanlisti - hicbir HTML sayfadan bir XML dosyasina link
        // verilmez, bu yuzden bu girdiler her zaman "yetim sayfa" ya da
        // "sitemap disi sayfa" olarak yanlis pozitif uretiyordu. Crawler
        // verildiyse alt-sitemap'lerin ICINE girip gercek sayfa URL'lerini
        // cikariyoruz; verilmediyse bu girdiler sessizce atlaniyor - bir daha
        // hicbir zaman sayfa listesine dogrudan eklenmiyorlar.
        $subSitemapUrls = [];
        foreach ($doc->sitemap ?? [] as $sitemap) {
            if (isset($sitemap->loc)) {
                $subSitemapUrls[] = trim((string) $sitemap->loc);
            }
        }

        if (!empty($subSitemapUrls) && $crawler !== null) {
            $subSitemapUrls = array_slice(array_values(array_unique(array_filter($subSitemapUrls))), 0, $maxSubSitemaps);
            $results = $crawler->fetchMultiple($subSitemapUrls, 5, false, true);
            foreach ($results as $result) {
                if (($result['error'] ?? null) !== null) {
                    continue;
                }
                $body = (string) ($result['body'] ?? '');
                if ($body === '') {
                    continue;
                }
                $prevInner = libxml_use_internal_errors(true);
                $subDoc = simplexml_load_string($body);
                libxml_use_internal_errors($prevInner);
                if ($subDoc === false) {
                    continue;
                }
                // NOT: alt-sitemap'in KENDISI de bir index olsa (nadir, cok
                // katmanli kurulumlarda), ikinci seviyeye inmiyoruz - sonsuz/
                // derin ozyineleme riskini almaktansa boyle durumlari eksik
                // birakmayi tercih ediyoruz.
                foreach ($subDoc->url ?? [] as $url) {
                    if (isset($url->loc)) {
                        $urls[] = trim((string) $url->loc);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Taranan sayfalar ile sitemap'teki URL'leri karşılaştırır:
     * - orphan_pages: sitede bulunan ama sitemap'te olmayan sayfalar
     * - sitemap_only: sitemap'te olan ama site içi taramada hiç linklenmemiş sayfalar
     *
     * @param list<string> $crawledUrls
     * @param list<string> $sitemapUrls
     * @return array{orphan_pages: list<string>, sitemap_only: list<string>, overlap_count: int}
     */
    public function crossReferenceSitemap(array $crawledUrls, array $sitemapUrls): array
    {
        $normalize = static fn (string $u): string => rtrim(strtolower(preg_replace('#^https?://(www\.)?#', '', $u) ?? $u), '/');

        $crawledNorm = array_map($normalize, $crawledUrls);
        $sitemapNorm = array_map($normalize, $sitemapUrls);

        $crawledSet = array_combine($crawledNorm, $crawledUrls) ?: [];
        $sitemapSet = array_combine($sitemapNorm, $sitemapUrls) ?: [];

        $orphanKeys = array_diff($crawledNorm, $sitemapNorm);
        $sitemapOnlyKeys = array_diff($sitemapNorm, $crawledNorm);

        return [
            'orphan_pages' => array_values(array_intersect_key($crawledSet, array_flip($orphanKeys))),
            'sitemap_only' => array_values(array_intersect_key($sitemapSet, array_flip($sitemapOnlyKeys))),
            'overlap_count' => count(array_intersect($crawledNorm, $sitemapNorm)),
        ];
    }

}

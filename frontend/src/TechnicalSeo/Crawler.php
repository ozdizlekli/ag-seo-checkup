<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * Saf PHP (cURL) tabanlı HTTP istemcisi.
 *
 * Yönlendirmeleri elle takip ediyoruz (CURLOPT_FOLLOWLOCATION=false) çünkü
 * zinciri adım adım görmemiz ve HER hop'ta SSRF kontrolü yapmamız gerekiyor.
 *
 * GÜVENLİK (SSRF): Kullanıcının verdiği URL'e (ve her yönlendirme hedefine)
 * istek atmadan önce: sadece http/https şemasına izin verilir, host DNS ile
 * çözümlenir, çözümlenen TÜM IP'ler (IPv4+IPv6) private/reserved/loopback/
 * link-local/CGNAT/multicast/dokümantasyon aralıklarına karşı kontrol edilir
 * (herhangi biri bile şüpheliyse istek tamamen reddedilir - fail closed),
 * ve doğrulanan IP CURLOPT_RESOLVE ile sabitlenir (DNS rebinding/TOCTOU'yu
 * önlemek için - kontrol ile gerçek bağlantı arasında curl'ün host'u tekrar
 * çözümlemesine izin vermeyiz).
 */
final class Crawler
{
    private string $userAgent;
    private int $timeout;
    private int $maxRedirects;

    /**
     * İstek-başına (request-scope) bellek-içi önbellek. Anahtar: HTTP metodu
     * (GET/HEAD) + User-Agent + fragment'i çıkarılmış URL. Bu nesne her HTTP
     * isteğinde sıfırdan oluşturulduğu için önbellek de doğal olarak istek
     * bazlı kalır. Mobil/masaüstü analizleri ayrı Crawler örnekleri kullanır
     * (fetchDualUserAgent), dolayısıyla otomatik izole olurlar.
     *
     * @var array<string, array<string,mixed>>
     */
    private array $fetchCache = [];

    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** SEO denetimi için makul bir üst sınır - devasa dosyaları (video, arşiv vb.) belleğe almayı engeller. */
    private const MAX_RESPONSE_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * PHP'nin filter_var(FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)
     * bayrakları RFC1918 private + loopback + link-local'ı yakalar ama şu
     * aralıkları YAKALAMAZ (canlı test ile doğrulandı) - bu yüzden elle ekliyoruz.
     *
     * @var list<string>
     */
    private const EXTRA_BLOCKED_CIDRS = [
        '100.64.0.0/10',   // Carrier-grade NAT (RFC 6598)
        '192.0.0.0/24',    // IETF protokol ayrımları
        '192.0.2.0/24',    // TEST-NET-1 (dokümantasyon)
        '198.18.0.0/15',   // Benchmark test (RFC 2544)
        '198.51.100.0/24', // TEST-NET-2 (dokümantasyon)
        '203.0.113.0/24',  // TEST-NET-3 (dokümantasyon)
        '224.0.0.0/4',     // IPv4 multicast
        '2001:db8::/32',   // IPv6 dokümantasyon
        'ff00::/8',        // IPv6 multicast
    ];

    public function __construct(string $userAgent, int $timeout = 12, int $maxRedirects = 6)
    {
        $this->userAgent = $userAgent;
        $this->timeout = $timeout;
        $this->maxRedirects = $maxRedirects;
    }

    /**
     * Bir URL'i getirir, yönlendirmeleri elle takip eder. İstek-başına önbelleklidir.
     *
     * @return array{
     *   final_url: string, status_code: int, headers: array<string,string>,
     *   body: string, redirect_chain: list<array{from:string,to:string,status:int}>,
     *   error: string|null, total_time_ms: int
     * }
     */
    public function fetch(string $url, bool $headOnly = false): array
    {
        $cacheKey = $this->cacheKey($url, $headOnly);
        if (array_key_exists($cacheKey, $this->fetchCache)) {
            return $this->fetchCache[$cacheKey];
        }

        $result = $this->fetchUncached($url, $headOnly);
        $this->fetchCache[$cacheKey] = $result;

        // Yönlendirme farklı bir URL'de bittiyse, o nihai adres için de aynı
        // sonucu önbelleğe yaz - aksi halde biri daha sonra o kanonik URL'i
        // fetch() ile çağırırsa (ör. redirect sonrası) yine ağa gidilir.
        if ($result['error'] === null && $result['final_url'] !== $url) {
            $finalKey = $this->cacheKey($result['final_url'], $headOnly);
            $this->fetchCache[$finalKey] ??= $result;
        }

        return $result;
    }

    /** Sadece fragment çıkarılır - trailing slash'a DOKUNULMAZ ("/a" ile "/a/" farklı kaynak olabilir). */
    private function cacheKey(string $url, bool $headOnly): string
    {
        $withoutFragment = strtok($url, '#');
        $normalized = $withoutFragment === false ? $url : $withoutFragment;
        return ($headOnly ? 'HEAD' : 'GET') . '|' . $this->userAgent . '|' . $normalized;
    }

    private function fetchUncached(string $url, bool $headOnly): array
    {
        $chain = [];
        $currentUrl = $url;
        $start = hrtime(true);

        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            $result = $this->singleRequest($currentUrl, $headOnly);

            if ($result['error'] !== null) {
                return $this->buildFetchResult($currentUrl, 0, [], '', $chain, $result['error'], $start);
            }

            $status = $result['status_code'];
            $isRedirect = in_array($status, [301, 302, 303, 307, 308], true);
            $location = $result['headers']['location'] ?? null;

            if ($isRedirect && $location !== null) {
                if ($hop === $this->maxRedirects) {
                    return $this->buildFetchResult(
                        $currentUrl, $status, $result['headers'], $result['body'], $chain,
                        'Çok fazla yönlendirme (olası döngü), ' . ($this->maxRedirects + 1) . ' adımdan sonra durduruldu.',
                        $start
                    );
                }

                $nextUrl = $this->resolveUrl($currentUrl, $location);
                $chain[] = ['from' => $currentUrl, 'to' => $nextUrl, 'status' => $status];
                $currentUrl = $nextUrl;
                continue;
            }

            return $this->buildFetchResult($currentUrl, $status, $result['headers'], $result['body'], $chain, null, $start);
        }

        return $this->buildFetchResult($currentUrl, 0, [], '', $chain, 'Beklenmeyen döngü sonu.', $start);
    }

    private function buildFetchResult(string $finalUrl, int $status, array $headers, string $body, array $chain, ?string $error, int|float $start): array
    {
        return [
            'final_url' => $finalUrl,
            'status_code' => $status,
            'headers' => $headers,
            'body' => $body,
            'redirect_chain' => $chain,
            'error' => $error,
            'total_time_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
        ];
    }

    /**
     * Aynı anda birden fazla URL'i (curl_multi ile paralel) getirir - kırık
     * link taraması gibi "sadece son durumu bilmem yeterli" senaryoları için.
     * Yönlendirmeleri curl'e bıraKMIYORUZ; fetch() ile aynı mantıkla tur tur
     * (round-by-round) elle takip ediyoruz ki her hop SSRF kontrolünden geçsin.
     *
     * $includeBody=true verilirse (ör. SiteStructureAnalyzer'ın paralel sayfa
     * taraması için) her sonuca 'body' ve 'headers' de eklenir - bu durumda
     * $headOnly=false ile çağrılmalıdır, aksi halde gövde boş döner.
     *
     * @param list<string> $urls
     * @return array<string, array{status_code:int, error:string|null, final_url:string, body?:string, headers?:array<string,string>}>
     */
    public function fetchMultiple(array $urls, int $concurrency = 10, bool $headOnly = true, bool $includeBody = false): array
    {
        $urls = array_values(array_unique($urls));
        if (empty($urls)) {
            return [];
        }

        $current = array_combine($urls, $urls); // originalUrl => o an takip edilen URL
        $final = [];

        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            if (empty($current)) {
                break;
            }

            $toDispatch = [];
            foreach ($current as $originalUrl => $curUrl) {
                $validation = $this->validateUrlForFetch($curUrl);
                if (!$validation['ok']) {
                    $final[$originalUrl] = $this->emptyResultEntry($curUrl, $validation['reason'], $includeBody);
                    unset($current[$originalUrl]);
                    continue;
                }
                $toDispatch[$originalUrl] = $validation;
            }

            if (empty($toDispatch)) {
                break;
            }

            foreach (array_chunk($toDispatch, max(1, $concurrency), true) as $chunk) {
                $this->runMultiChunk($chunk, $current, $final, $headOnly, $hop, $includeBody);
            }
        }

        // Döngü tüm hop'ları tükettiği halde hâlâ takip edilen bir şey kaldıysa
        // (normal şartlarda olmaz - hop===maxRedirects durumu runMultiChunk
        // içinde zaten sonuçlandırılıyor - ama savunma amaçlı bırakıyoruz).
        foreach ($current as $originalUrl => $curUrl) {
            $final[$originalUrl] = $this->emptyResultEntry(
                $curUrl,
                'Çok fazla yönlendirme (olası döngü), ' . ($this->maxRedirects + 1) . ' adımdan sonra durduruldu.',
                $includeBody
            );
        }

        $ordered = [];
        foreach ($urls as $u) {
            $ordered[$u] = $final[$u] ?? $this->emptyResultEntry($u, 'Bilinmeyen hata.', $includeBody);
        }

        return $ordered;
    }

    /** @return array{status_code:int, error:string|null, final_url:string, body?:string, headers?:array<string,string>} */
    private function emptyResultEntry(string $url, string $error, bool $includeBody): array
    {
        $entry = ['status_code' => 0, 'error' => $error, 'final_url' => $url];
        if ($includeBody) {
            $entry['body'] = '';
            $entry['headers'] = [];
        }
        return $entry;
    }

    /**
     * Tek bir eşzamanlılık grubunu (chunk) çalıştırır; sonuçları $final'e
     * yazar, yönlendirilenleri $current'ta bir sonraki hop için günceller.
     *
     * @param array<string, array{ok:bool,reason:?string,host?:string,port?:int,resolvedIp?:string}> $chunk
     */
    private function runMultiChunk(array $chunk, array &$current, array &$final, bool $headOnly, int $hop, bool $includeBody = false): void
    {
        $multiHandle = curl_multi_init();
        $handles = [];
        $buffers = [];

        foreach ($chunk as $originalUrl => $validation) {
            $ch = curl_init();
            curl_setopt_array($ch, $this->curlBaseOptions($current[$originalUrl], $headOnly, $validation));
            $buffers[$originalUrl] = '';
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($handle, $chunkData) use (&$buffers, $originalUrl): int {
                $buffers[$originalUrl] .= $chunkData;
                return strlen($buffers[$originalUrl]) > self::MAX_RESPONSE_BYTES ? 0 : strlen($chunkData);
            });
            curl_multi_add_handle($multiHandle, $ch);
            $handles[$originalUrl] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($running) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        // curl_multi bağlamında curl_errno($ch) güvenilmez olabilir - gerçek
        // sonuç kodu SADECE curl_multi_info_read() ile okunur.
        $resultByHandleId = [];
        while ($info = curl_multi_info_read($multiHandle)) {
            $resultByHandleId[spl_object_id($info['handle'])] = $info['result'];
        }

        foreach ($handles as $originalUrl => $ch) {
            $curlResult = $resultByHandleId[spl_object_id($ch)] ?? CURLE_OK;
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers = $this->parseHeaders(substr($buffers[$originalUrl], 0, $headerSize));

            if ($curlResult !== CURLE_OK) {
                $final[$originalUrl] = $this->emptyResultEntry(
                    $current[$originalUrl],
                    $curlResult === CURLE_WRITE_ERROR
                        ? 'Yanıt boyutu izin verilen üst sınırı aştığı için bağlantı kesildi.'
                        : (curl_strerror($curlResult) ?? ('curl hata kodu ' . $curlResult)),
                    $includeBody
                );
                unset($current[$originalUrl]);
            } else {
                $isRedirect = in_array($httpCode, [301, 302, 303, 307, 308], true);
                $location = $headers['location'] ?? null;

                if ($isRedirect && $location !== null) {
                    if ($hop === $this->maxRedirects) {
                        // fetch() ile AYNI davranış: limitte hâlâ yönlendiriliyorsa hata say, "başarılı 301" değil.
                        $final[$originalUrl] = $this->emptyResultEntry(
                            $current[$originalUrl],
                            'Çok fazla yönlendirme (olası döngü), ' . ($this->maxRedirects + 1) . ' adımdan sonra durduruldu.',
                            $includeBody
                        );
                    } else {
                        $current[$originalUrl] = $this->resolveUrl($current[$originalUrl], $location);
                        continue; // henüz sonuçlandırma, bir sonraki hop'a devret
                    }
                } else {
                    $final[$originalUrl] = ['status_code' => $httpCode, 'error' => null, 'final_url' => $current[$originalUrl]];
                    if ($includeBody) {
                        $final[$originalUrl]['body'] = substr($buffers[$originalUrl], $headerSize);
                        $final[$originalUrl]['headers'] = $headers;
                    }
                }
                unset($current[$originalUrl]);
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
    }

    /**
     * Aynı URL'i masaüstü + gerçek Googlebot mobil User-Agent'ı ile çeker
     * (mobil-öncelikli indeksleme karşılaştırması için). Ayrı Crawler
     * örnekleri kullanır, dolayısıyla önbellekleri de otomatik izole olur.
     *
     * DÜRÜST SINIRLAMA: JavaScript render etmez - sadece sunucunun UA'ya göre
     * farklı ham HTML döndürdüğü (dynamic/adaptive serving) durumları yakalar.
     */
    public function fetchDualUserAgent(string $url, string $desktopUa, string $mobileUa): array
    {
        $desktop = new self($desktopUa, $this->timeout, $this->maxRedirects);
        $mobile = new self($mobileUa, $this->timeout, $this->maxRedirects);

        return ['desktop' => $desktop->fetch($url), 'mobile' => $mobile->fetch($url)];
    }

    /**
     * @return array{status_code:int, headers:array<string,string>, body:string, error:string|null}
     */
    private function singleRequest(string $url, bool $headOnly): array
    {
        $validation = $this->validateUrlForFetch($url);
        if (!$validation['ok']) {
            return ['status_code' => 0, 'headers' => [], 'body' => '', 'error' => $validation['reason']];
        }

        $buffer = '';
        $ch = curl_init();
        curl_setopt_array($ch, $this->curlBaseOptions($url, $headOnly, $validation));
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($handle, $chunk) use (&$buffer): int {
            $buffer .= $chunk;
            return strlen($buffer) > self::MAX_RESPONSE_BYTES ? 0 : strlen($chunk);
        });

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);

        if ($ok === false || $errno !== 0) {
            $error = $errno === CURLE_WRITE_ERROR
                ? 'Yanıt boyutu izin verilen üst sınırı (' . (int) (self::MAX_RESPONSE_BYTES / 1024 / 1024) . ' MB) aştığı için bağlantı kesildi.'
                : curl_error($ch);
            curl_close($ch);
            return ['status_code' => 0, 'headers' => [], 'body' => '', 'error' => $error];
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'status_code' => $statusCode,
            'headers' => $this->parseHeaders(substr($buffer, 0, $headerSize)),
            'body' => substr($buffer, $headerSize),
            'error' => null,
        ];
    }

    /**
     * @param array{host:string,port:int,resolvedIp:string} $validation
     * @return array<int,mixed>
     */
    private function curlBaseOptions(string $url, bool $headOnly, array $validation): array
    {
        $resolveTarget = str_contains($validation['resolvedIp'], ':') ? '[' . $validation['resolvedIp'] . ']' : $validation['resolvedIp'];

        return [
            CURLOPT_URL => $url,
            CURLOPT_FOLLOWLOCATION => false, // yönlendirmeler HER ZAMAN elle takip edilir (SSRF kontrolü şart)
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => $headOnly,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // Doğrulanan IP'yi sabitle - curl bu host:port için kendi DNS'ini kullanmasın (rebinding/TOCTOU önlemi).
            CURLOPT_RESOLVE => ["{$validation['host']}:{$validation['port']}:{$resolveTarget}"],
        ];
    }

    /**
     * SSRF kontrolü: şema + DNS çözümleme + private/reserved/CGNAT/multicast/
     * dokümantasyon aralığı kontrolü. Fail-closed: çözümlenen IP'lerden
     * herhangi biri güvensizse ya da hiçbiri çözümlenemiyorsa reddedilir.
     *
     * @return array{ok:bool, reason:?string, host?:string, port?:int, resolvedIp?:string}
     */
    private function validateUrlForFetch(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host']) || $parts['host'] === '') {
            return ['ok' => false, 'reason' => 'Geçersiz veya host içermeyen URL.'];
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return ['ok' => false, 'reason' => 'Yalnızca http/https adresleri desteklenir (' . ($scheme !== '' ? $scheme : 'şema yok') . ' reddedildi).'];
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $ips = $this->resolveHostIps($host);
        if (empty($ips)) {
            return ['ok' => false, 'reason' => "Host çözümlenemedi: {$host}"];
        }

        $publicIp = null;
        foreach ($ips as $ip) {
            if (!$this->isIpPublic($ip)) {
                return ['ok' => false, 'reason' => "Hedef IP özel/ayrılmış bir aralıkta olduğu için reddedildi (SSRF koruması): {$host} -> {$ip}"];
            }
            $publicIp ??= $ip;
        }

        return ['ok' => true, 'reason' => null, 'host' => $host, 'port' => $port, 'resolvedIp' => (string) $publicIp];
    }

    /** @return list<string> */
    private function resolveHostIps(string $host): array
    {
        $bareHost = trim($host, '[]');

        if (filter_var($bareHost, FILTER_VALIDATE_IP) !== false) {
            return [$bareHost];
        }

        $ips = [];

        $aRecords = @gethostbynamel($bareHost);
        if (is_array($aRecords)) {
            $ips = array_merge($ips, $aRecords);
        }

        $aaaaRecords = @dns_get_record($bareHost, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Native filter_var bayrakları private (RFC1918/RFC4193) + reserved
     * (loopback, link-local vb.) aralıkları kapsar; CGNAT/benchmark/
     * multicast/dokümantasyon aralıklarını EXTRA_BLOCKED_CIDRS ile ekliyoruz.
     */
    private function isIpPublic(string $ip): bool
    {
        // IPv4-mapped IPv6 (ör. ::ffff:127.0.0.1) - gömülü IPv4'ü kontrol et.
        $toCheck = preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $m) ? $m[1] : $ip;

        if (filter_var($toCheck, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach (self::EXTRA_BLOCKED_CIDRS as $cidr) {
            if ($this->ipInCidr($toCheck, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskBits] = explode('/', $cidr);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false; // farklı adres ailesi (v4/v6) ya da geçersiz
        }

        $maskBits = (int) $maskBits;
        $bytes = intdiv($maskBits, 8);
        $remainderBits = $maskBits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($remainderBits > 0) {
            $mask = chr((0xFF << (8 - $remainderBits)) & 0xFF);
            if ((substr($ipBin, $bytes, 1) & $mask) !== (substr($subnetBin, $bytes, 1) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,string> */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        // Aynı yanıtta birden fazla header bloğu olabilir (100 Continue vb.) - sadece SONuncuya bak.
        $blocks = preg_split('/\r\n\r\n|\n\n/', trim($rawHeaders)) ?: [];
        $lines = preg_split('/\r\n|\n/', (string) end($blocks)) ?: [];

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    /**
     * Göreli/kısmi bir Location header'ını RFC 3986 §5.3 mantığıyla base URL'e
     * göre çözer - mutlak, protokol-göreli (//), kök-göreli (/x), sadece-query
     * (?x=1), sadece-fragment (#x) ve "../"lı göreli yolların hepsini kapsar.
     */
    private function resolveUrl(string $baseUrl, string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return $baseUrl;
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl) ?: [];
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':' . $base['port'] : '';
        $authority = $scheme . '://' . $host . $port;

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        $loc = parse_url($location) ?: [];
        $basePath = ($base['path'] ?? '') !== '' ? $base['path'] : '/';

        if (!isset($loc['path']) || $loc['path'] === '') {
            // Sadece query ve/veya fragment değişiyor - path aynı kalır (RFC 3986 §5.3).
            $path = $basePath;
            $query = array_key_exists('query', $loc) ? $loc['query'] : ($base['query'] ?? null);
        } elseif (str_starts_with($loc['path'], '/')) {
            $path = $this->removeDotSegments($loc['path']);
            $query = $loc['query'] ?? null;
        } else {
            $lastSlash = strrpos($basePath, '/');
            $dir = $lastSlash !== false ? substr($basePath, 0, $lastSlash + 1) : '/';
            $path = $this->removeDotSegments($dir . $loc['path']);
            $query = $loc['query'] ?? null;
        }

        $result = $authority . $path;
        if ($query !== null) {
            $result .= '?' . $query;
        }
        if (isset($loc['fragment'])) {
            $result .= '#' . $loc['fragment'];
        }

        return $result;
    }

    /** RFC 3986 §5.2.4 "remove dot segments" - "/a/b/../c" -> "/a/c", "/a/./b" -> "/a/b". */
    private function removeDotSegments(string $path): string
    {
        $output = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if (!empty($output) && end($output) !== '') {
                    array_pop($output);
                }
                continue;
            }
            $output[] = $segment;
        }

        $result = implode('/', $output);
        return $result === '' ? '/' : $result;
    }
}

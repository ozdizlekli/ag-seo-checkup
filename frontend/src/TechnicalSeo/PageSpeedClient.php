<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * Google PageSpeed Insights v5 API istemcisi - SUNUCU tarafında çalışır.
 *
 * ÖNEMLİ: Eski kodda API anahtarı (PAGESPEED_API_KEY) doğrudan js/app.js
 * içinde, tarayıcıda görünür şekilde duruyordu. Bu bir güvenlik açığıydı:
 * herkes "Görünüm Kaynağını Göster" ile anahtarı kopyalayıp kendi kotasını
 * bu anahtar üzerinden tüketebilirdi. Burada anahtar sadece sunucuda,
 * .env dosyasında tutulur; tarayıcı sadece bizim api/technical_seo_audit.php
 * uç noktamıza URL gönderir, PSI'a giden istek sunucudan yapılır
 * (gemini_proxy.php'deki ile aynı desen).
 *
 * PSI zaten Google'ın kendi Lighthouse motorunu (log-normal eğri ile 0-100
 * skorlama) sunucu tarafında çalıştırıp bize hazır skor döndürüyor - bu
 * yüzden skorlama eğrisini burada yeniden icat etmiyoruz, Google'ınkini
 * kullanıyoruz. Bizim katkımız: mobil+masaüstü paralel çekmek, lab/field
 * verisi tutarsızlığını tespit etmek ve bunu kendi ScoringEngine'imizdeki
 * "performans" kategorisine girdi olarak vermek.
 */
final class PageSpeedClient
{
    private string $apiKey;
    private string $endpoint;
    private int $timeout;

    private const CATEGORIES = ['performance', 'seo', 'accessibility', 'best-practices'];

    public function __construct(string $apiKey, string $endpoint, int $timeout = 60)
    {
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
        $this->timeout = $timeout;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Mobil ve masaüstü stratejilerini curl_multi ile PARALEL çeker.
     * Sıralı (sequential) yapsaydık toplam süre ~2 katına çıkardı; PSI
     * çağrıları zaten 10-40 saniye sürebildiği için bu önemli.
     *
     * @return array{mobile: array|null, desktop: array|null, errors: array<string,string>}
     */
    public function analyzeBoth(string $url): array
    {
        if (!$this->hasApiKey()) {
            return ['mobile' => null, 'desktop' => null, 'errors' => [
                'mobile' => 'PAGESPEED_API_KEY .env dosyasında tanımlı değil.',
                'desktop' => 'PAGESPEED_API_KEY .env dosyasında tanımlı değil.',
            ]];
        }

        $urls = [
            'mobile' => $this->buildRequestUrl($url, 'mobile'),
            'desktop' => $this->buildRequestUrl($url, 'desktop'),
        ];

        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($urls as $strategy => $requestUrl) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $requestUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[$strategy] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($running) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        // ÖNEMLİ: curl_multi bağlamında curl_errno($ch) GÜVENİLMEZ olabilir -
        // gerçek transfer sonucu SADECE curl_multi_info_read() ile okunur
        // (bkz. Crawler.php'de aynı sınıf hatayı bulup düzelttiğimiz yer).
        // Bu düzeltilmeden önce gerçek bir ağ hatası sessizce "başarılı"
        // sayılıp boş/bozuk gövde json_decode'a gidiyor, sonuçta kullanıcıya
        // anlamsız "PageSpeed yanıtı ayrıştırılamadı" mesajı görünüyordu.
        $resultByHandleId = [];
        while ($info = curl_multi_info_read($multiHandle)) {
            $resultByHandleId[spl_object_id($info['handle'])] = $info['result'];
        }

        $results = ['mobile' => null, 'desktop' => null, 'errors' => []];

        foreach ($handles as $strategy => $ch) {
            $body = curl_multi_getcontent($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlResult = $resultByHandleId[spl_object_id($ch)] ?? CURLE_OK;

            if ($curlResult !== CURLE_OK) {
                $results['errors'][$strategy] = 'Ağ hatası: ' . (curl_strerror($curlResult) ?? ('curl hata kodu ' . $curlResult));
            } elseif ($httpCode === 429) {
                $results['errors'][$strategy] = 'PageSpeed API kotası aşıldı (429). Birkaç dakika sonra tekrar deneyin.';
            } elseif ($httpCode >= 400) {
                $decoded = json_decode((string) $body, true);
                $message = is_array($decoded) ? ($decoded['error']['message'] ?? "HTTP {$httpCode}") : "HTTP {$httpCode}";
                $results['errors'][$strategy] = "HTTP {$httpCode}: {$message}";
            } else {
                $decoded = json_decode((string) $body, true);
                $results[$strategy] = is_array($decoded) ? $this->extractSummary($decoded) : null;
                if ($results[$strategy] === null) {
                    // Tanı için: gerçekte ne döndüğünü (JSON hatası + gövdenin
                    // ilk 200 karakteri) mesaja ekliyoruz - "ayrıştırılamadı"
                    // tek başına neyin yanlış gittiğini anlamaya yetmiyordu.
                    $bodyPreview = trim(mb_substr((string) $body, 0, 200));
                    $results['errors'][$strategy] = sprintf(
                        'PageSpeed yanıtı ayrıştırılamadı (HTTP %d, json hatası: %s%s).',
                        $httpCode,
                        json_last_error_msg(),
                        $bodyPreview !== '' ? ', gövde: ' . $bodyPreview : ', gövde boş'
                    );
                }
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        if ($results['mobile'] !== null && $results['desktop'] !== null) {
            $results['lab_field_mismatch'] = $this->detectLabFieldMismatch($results['mobile']);
        }

        return $results;
    }

    private function buildRequestUrl(string $url, string $strategy): string
    {
        $params = [
            'url' => $url,
            'strategy' => $strategy,
            'key' => $this->apiKey,
        ];

        $query = http_build_query($params);
        foreach (self::CATEGORIES as $category) {
            $query .= '&category=' . urlencode($category);
        }

        return $this->endpoint . '?' . $query;
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{
     *   category_scores: array<string,int|null>,
     *   web_vitals: array<string, array{numeric_value: float|null, display_value: string|null}>,
     *   field_data: array<string, mixed>|null
     * }
     */
    private function extractSummary(array $raw): array
    {
        $categories = $raw['lighthouseResult']['categories'] ?? [];
        $audits = $raw['lighthouseResult']['audits'] ?? [];

        $categoryScores = [];
        foreach (self::CATEGORIES as $category) {
            $score = $categories[$category]['score'] ?? null;
            $categoryScores[$category] = $score !== null ? (int) round($score * 100) : null;
        }

        $vitalsAuditKeys = [
            'lcp' => 'largest-contentful-paint',
            'fcp' => 'first-contentful-paint',
            'cls' => 'cumulative-layout-shift',
            'ttfb' => 'server-response-time',
            'inp' => 'interaction-to-next-paint',
            'tbt' => 'total-blocking-time',
            'speed_index' => 'speed-index',
        ];

        // CrUX (gerçek kullanıcı) alan verisi - varsa. Önce sayfaya özel
        // (loadingExperience), o yoksa site geneline ait (originLoadingExperience)
        // veriyi kullanıyoruz. Yeterli trafiği olmayan siteler/sayfalar için
        // Google ikisini de döndürmeyebilir.
        $fieldData = $raw['loadingExperience']['metrics'] ?? $raw['originLoadingExperience']['metrics'] ?? null;
        $fieldDataIsOriginLevel = !isset($raw['loadingExperience']['metrics']) && isset($raw['originLoadingExperience']['metrics']);

        $webVitals = [];
        foreach ($vitalsAuditKeys as $key => $auditKey) {
            $audit = $audits[$auditKey] ?? null;
            $numericValue = is_array($audit) && isset($audit['numericValue']) ? (float) $audit['numericValue'] : null;
            $displayValue = is_array($audit) ? ($audit['displayValue'] ?? null) : null;

            // INP, Lighthouse'un laboratuvar (simüle) testinde neredeyse hiç
            // bulunmaz - çünkü gerçek bir kullanıcı tıklaması/dokunuşu
            // gerektirir, sayfayı sadece açıp kapatan bir test bunu üretemez.
            // Lab verisi yoksa, varsa CrUX saha verisine (gerçek kullanıcı
            // ölçümlerine) düşüyoruz - INP'nin saha metriği zaten milisaniye
            // cinsinden geliyor, lab'daki numericValue ile aynı birim.
            if ($key === 'inp' && $numericValue === null && is_array($fieldData) && isset($fieldData['INTERACTION_TO_NEXT_PAINT']['percentile'])) {
                $numericValue = (float) $fieldData['INTERACTION_TO_NEXT_PAINT']['percentile'];
                $displayValue = round($numericValue) . ' ms (gerçek kullanıcı verisi'
                    . ($fieldDataIsOriginLevel ? ', site geneli' : '') . ')';
            }

            // TTFB (server-response-time) icin Lighthouse bazen audit hic
            // uygulanamadiginda (notApplicable/error) bile numericValue'yu 0
            // olarak dondurebiliyor - gercek bir sunucunun yanit suresi asla
            // tam olarak 0ms olamayacagi icin bunu "veri yok" olarak
            // yorumluyoruz, aksi halde arayuzde yaniltici sekilde "0 ms"
            // (ve yesil basari cubugu) gosteriliyordu.
            if ($key === 'ttfb' && $numericValue !== null && $numericValue <= 0) {
                $numericValue = null;
                $displayValue = null;
            }

            $webVitals[$key] = [
                'numeric_value' => $numericValue,
                'display_value' => $displayValue,
            ];
        }

        return [
            'category_scores' => $categoryScores,
            'web_vitals' => $webVitals,
            'field_data' => is_array($fieldData) ? $fieldData : null,
            'field_data_scope' => is_array($fieldData) ? ($fieldDataIsOriginLevel ? 'origin' : 'url') : null,
            'origin_data' => isset($raw['originLoadingExperience']['metrics']) && is_array($raw['originLoadingExperience']['metrics'])
                ? $raw['originLoadingExperience']['metrics']
                : null,
            'audits' => $this->extractAudits($audits),
        ];
    }

    /**
     * Lighthouse denetimlerini arayüz için güvenli, sınırlı ve geriye uyumlu
     * bir özete dönüştürür. Ham JSON veya ekran görüntüsü/base64 verisi
     * istemciye taşınmaz; ayrıntı satırları denetim başına 50 ile sınırlıdır.
     *
     * @param array<string,mixed> $audits
     * @return list<array<string,mixed>>
     */
    private function extractAudits(array $audits): array
    {
        $result = [];
        foreach ($audits as $id => $audit) {
            if (!is_array($audit)) {
                continue;
            }
            $mode = (string) ($audit['scoreDisplayMode'] ?? 'informative');
            $score = isset($audit['score']) && is_numeric($audit['score']) ? (float) $audit['score'] : null;
            $details = is_array($audit['details'] ?? null) ? $audit['details'] : [];
            $items = [];
            foreach (array_slice(is_array($details['items'] ?? null) ? $details['items'] : [], 0, 50) as $item) {
                if (!is_array($item)) continue;
                $safe = [];
                foreach ($item as $key => $value) {
                    if (is_scalar($value) || $value === null) {
                        $safe[(string) $key] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
                    } elseif (is_array($value) && isset($value['url']) && is_string($value['url'])) {
                        $safe[(string) $key] = ['url' => mb_substr($value['url'], 0, 2000)];
                    }
                }
                if ($safe !== []) $items[] = $safe;
            }
            $result[] = [
                'id' => (string) $id,
                'title' => (string) ($audit['title'] ?? $id),
                'description' => mb_substr(strip_tags((string) ($audit['description'] ?? '')), 0, 2000),
                'score' => $score,
                'score_display_mode' => $mode,
                'display_value' => isset($audit['displayValue']) ? (string) $audit['displayValue'] : null,
                'numeric_value' => isset($audit['numericValue']) && is_numeric($audit['numericValue']) ? (float) $audit['numericValue'] : null,
                'numeric_unit' => isset($audit['numericUnit']) ? (string) $audit['numericUnit'] : null,
                'savings_ms' => isset($details['overallSavingsMs']) && is_numeric($details['overallSavingsMs']) ? (float) $details['overallSavingsMs'] : null,
                'savings_bytes' => isset($details['overallSavingsBytes']) && is_numeric($details['overallSavingsBytes']) ? (float) $details['overallSavingsBytes'] : null,
                'details_type' => isset($details['type']) ? (string) $details['type'] : null,
                'items' => $items,
            ];
        }
        return $result;
    }

    /**
     * Lab (Lighthouse simülasyonu) ile field (gerçek kullanıcı/CrUX) verisi
     * arasında büyük bir fark varsa işaretler. Örn: lab'da LCP iyi ama gerçek
     * kullanıcılarda kötüyse, bu genelde "test sunucusu/ağı gerçek
     * kullanıcılardan çok daha hızlı" anlamına gelir - laboratuvar skoruna
     * körü körüne güvenmemek gerektiğinin işareti.
     *
     * @param array{web_vitals: array<string,array{numeric_value: float|null}>, field_data: array<string,mixed>|null} $mobileResult
     */
    private function detectLabFieldMismatch(array $mobileResult): array
    {
        $labLcpMs = $mobileResult['web_vitals']['lcp']['numeric_value'] ?? null;
        $fieldLcpMs = $mobileResult['field_data']['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? null;

        if ($labLcpMs === null || $fieldLcpMs === null) {
            return ['has_field_data' => $fieldLcpMs !== null, 'mismatch' => false, 'note' => 'Yeterli gerçek kullanıcı (CrUX) verisi yok.'];
        }

        // Google'ın CWV eşiği: LCP <= 2500ms iyi, > 4000ms kötü.
        $labGood = $labLcpMs <= 2500;
        $fieldPoor = $fieldLcpMs > 4000;

        return [
            'has_field_data' => true,
            'mismatch' => $labGood && $fieldPoor,
            'lab_lcp_ms' => $labLcpMs,
            'field_lcp_ms' => $fieldLcpMs,
            'note' => $labGood && $fieldPoor
                ? 'Laboratuvar testinde LCP iyi görünüyor ama gerçek kullanıcılarda kötü - test ortamı gerçek kullanıcı koşullarını (yavaş ağ/cihaz) yansıtmıyor olabilir.'
                : null,
        ];
    }
}

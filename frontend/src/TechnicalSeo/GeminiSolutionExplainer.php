<?php
declare(strict_types=1);

namespace App\TechnicalSeo;

final class GeminiSolutionException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $responseCode = 'GEMINI_REQUEST_FAILED',
        public readonly int $httpStatus = 503
    ) {
        parent::__construct($message);
    }
}

final class GeminiSolutionExplainer
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const DEFAULT_MODEL = 'gemini-3.5-flash-lite';
    private const TIMEOUT = 45;
    private const MAX_ATTEMPTS = 2;

    private readonly ?\Closure $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport !== null ? \Closure::fromCallable($transport) : null;
    }

    /** @return array{summary:string,steps:array<int,string>,example:string,warning:string} */
    public function explain(array $finding): array
    {
        $apiKey = technicalSeoEnv('GEMINI_API_KEY', '');
        if ($apiKey === '' || $apiKey === 'BURAYA_API_KEY_GELECEK') {
            throw new GeminiSolutionException('Gemini yapılandırması eksik.', 'GEMINI_NOT_CONFIGURED');
        }

        $title = trim((string) ($finding['title'] ?? ''));
        $detail = trim((string) ($finding['detail'] ?? ''));
        if ($title === '' && $detail === '') {
            throw new GeminiSolutionException('Geçerli bulgu verisi yok.', 'INVALID_FINDING', 400);
        }

        $solutionType = trim((string) ($finding['solution_type'] ?? ''));
        $trustedUrls = self::extractTrustedUrls($finding['items'] ?? []);
        $deterministicAsset = in_array($solutionType, ['generated_sitemap', 'generated_robots'], true);
        $schemaContext = $solutionType === 'json_ld'
            ? self::extractSchemaContext($finding['items'] ?? [], $trustedUrls)
            : null;
        $orphanContext = $solutionType === 'internal_link_suggestion'
            ? self::extractOrphanContext($finding['items'] ?? [], $trustedUrls)
            : null;
        // H1 eksik bulgusunda AI'a artik H1 METNI URETTIRMIYORUZ - her sayfanin
        // onerisi PHP/JS tarafinda sayfanin gercek <title> etiketinden birebir,
        // deterministik olarak turetilip ayri bir listede gosteriliyor (bkz.
        // js/technical-seo.js buildH1ReadyRecords). AI'in rolu asagida sadece bu
        // listeyi kalite acisindan gozden gecirip warning yazmak.
        // Prompt talimati AI'in her zaman uyacagi bir garanti degil - bu
        // yuzden "kaynak sayfa/gercek veri bulunamadi" durumunda example
        // alanini deterministicAsset ile ayni mekanizmayla (normalizeSolution)
        // PHP tarafinda da zorunlu olarak bosaltiyoruz.
        // Yinelenen title/meta description bulgularinda "hangi sayfa canonical
        // olsun" veya "her sayfaya ne yazilsin" sorusunun gercek cevabi sayfa
        // icerigine bakmadan bilinemez - bu yuzden example alanini burada da
        // zorla bosaltiyoruz (AI'in ornegin ustunde uydurma bir meta
        // description metni uretip "iste bu" demesini onlemek icin - bu
        // gercekten yasandi, bkz. asagidaki talimat metni).
        $isDuplicateContent = self::isDuplicateContentFinding($title, $detail);
        // Canonical-grubu inceleme bulgulari ("Canonical incelemesi gerekli",
        // "Canonical hedefi bozuk/yonlendiriyor/dongude", "Canonical etiketinde
        // yapisal hata") - hepsi solutionType='code_snippet' VE items dolu (tek
        // sayfalik ana-sayfa-canonical-eksik durumu ise items BOS gelir, o farkli
        // bir yoldan zaten guvenli). Bu bulgularda "hangi sayfa asil/canonical
        // olmali" sorusunun cevabi ScoringEngine'in KENDISI TARAFINDAN bile
        // bilinmiyor (bkz. ScoringEngine yorumu: "canonical hedefinin ICERIK
        // olarak da dogru sayfa oldugu bu otomatik denetimle dogrulanamaz") -
        // ama AI canli testte tam olarak bunu yapip bir sayfayi "dogru birincil
        // URL" ilan etti (gercekten yasandi). Bu yuzden ayni sekilde example'i
        // zorla bosaltiyoruz.
        $isCanonicalGroupFinding = $solutionType === 'code_snippet'
            && is_array($finding['items'] ?? null) && $finding['items'] !== [];
        $forceEmptyExample = $deterministicAsset
            || ($solutionType === 'internal_link_suggestion' && $orphanContext === null)
            || $solutionType === 'h1_suggestion'
            || $isDuplicateContent
            || $isCanonicalGroupFinding;
        $prompt = $this->buildPrompt($finding, $title, $detail, $solutionType, $trustedUrls, $deterministicAsset, $schemaContext, $orphanContext);
        $model = technicalSeoEnv('GEMINI_MODEL', self::DEFAULT_MODEL);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $payload = $this->buildPayload($prompt, $attempt === self::MAX_ATTEMPTS);
            $result = $this->requestGemini($apiKey, $model, $payload);
            [$text, $candidateCount, $finishReasons] = $this->extractCandidateText($result);

            $parseError = 'No error';
            $data = self::decodeStructuredText($text, $parseError);
            if ($data !== null) {
                return $this->normalizeSolution($data, $trustedUrls, $forceEmptyExample);
            }

            error_log(
                '[Technical AI] Gemini çözüm JSON parse hatası; attempt=' . $attempt
                . ', jsonError=' . $parseError
                . ', responseLength=' . strlen($text)
                . ', candidates=' . $candidateCount
                . ', finishReason=' . implode(',', array_unique($finishReasons))
            );

            if ($attempt === self::MAX_ATTEMPTS) {
                throw new GeminiSolutionException(
                    'Gemini çözümü JSON biçiminde değildi.',
                    'GEMINI_JSON_PARSE_ERROR',
                    502
                );
            }
        }

        throw new GeminiSolutionException('Gemini geçersiz bir yanıt döndürdü.', 'GEMINI_INVALID_RESPONSE', 502);
    }

    /**
     * @param list<string> $trustedUrls
     * @param array{url:string, site_name:string, description:string}|null $schemaContext
     * @param array{orphan_url:string, orphan_title:string, candidate_url:string, candidate_title:string}|null $orphanContext
     */
    private function buildPrompt(
        array $finding,
        string $title,
        string $detail,
        string $solutionType,
        array $trustedUrls,
        bool $deterministicAsset,
        ?array $schemaContext = null,
        ?array $orphanContext = null
    ): string {
        $trustedUrlContext = $trustedUrls === []
            ? 'Güvenilir gerçek URL yok. example alanını boş bırak.'
            : "Yalnızca şu gerçek analiz URL'lerini kullanabilirsin: " . implode(', ', $trustedUrls);
        $assetInstruction = $deterministicAsset
            ? 'Sistem bu bulgu için hazır dosya üretiyor. Sitemap/robots içeriği üretme; example boş olsun ve kullanıcıyı ekrandaki “Hazır dosya” işlemine yönlendir.'
            : '';
        // "example alanina uydurma bir title/meta description metni yazma"
        // acikca soyleniyor - bu talimat olmadan AI, sayfanin gercek
        // icerigini bilmeden (crawl bu veriyi vermiyor) inandirici ama
        // TAMAMEN UYDURMA bir aciklama/tit metni yazip "kullan bunu" diyordu.
        // forceEmptyExample zaten example'i kod tarafinda da bosaltiyor -
        // bu talimat AI'in steps/summary alaninda da ayni hatayi yapmamasi icin.
        $duplicateContentInstruction = self::isDuplicateContentFinding($title, $detail)
            ? 'Hangi sayfanın canonical olacağına dair kesin karar verme; farklı içeriklerde benzersiz title/description '
                . 'seçeneğiyle aynı içeriği temsil eden filtre/parametre varyasyonlarında teknik inceleme sonrası canonical '
                . 'veya indeksleme stratejisi seçeneğini açıkça ayır. example alanına ASLA uydurma bir title veya meta '
                . 'description METNİ yazma - sayfanın gerçek içeriğini bilmiyorsun, bu yüzden example boş kalacak; '
                . 'sadece steps alanında yöntemi anlat.'
            : '';
        // Canonical-grubu inceleme bulgularinda (bkz. explain() - solutionType
        // 'code_snippet' + items dolu) AI'in HANGI SAYFANIN asil/canonical
        // olmasi gerektigine karar vermesi/ornek URL secmesi YASAK - bu bir
        // icerik degerlendirmesi ve sistemin kendisi bile bunu bilmiyor.
        $canonicalGroupInstruction = ($solutionType === 'code_snippet' && is_array($finding['items'] ?? null) && $finding['items'] !== [])
            ? 'Bu bulguda hangi sayfanın "asıl"/canonical sayfa olması gerektiği ScoringEngine tarafından bile '
                . 'belirlenemedi (bkz. bulgunun kendi açıklaması) - bu SADECE sayfaların gerçek içeriğine bakan bir '
                . 'insan tarafından karar verilebilir. SEN de hangi URL\'nin doğru/birincil canonical hedefi '
                . 'olduğuna KESİNLİKLE karar verme veya bir URL\'yi "doğru birincil URL" gibi sunma; example '
                . 'alanını KESİNLİKLE boş bırak - belirli bir canonical hedefi içeren kod üretme. steps alanında '
                . 'sadece YÖNTEMİ anlat: kullanıcının her grubu elle incelemesi, sayfaların gerçekten aynı içeriği '
                . 'gösterip göstermediğine bakması ve öyleyse hangisinin birincil sayfa olacağına kendisinin karar '
                . 'vermesi gerektiğini belirt.'
            : '';
        // Yalnızca ScoringEngine'in ZATEN BİLİNEN, gerçek site verisiyle
        // (site adı/title, URL, meta açıklaması) doldurduğu şema bulgularında
        // dolu - AI'a bu dışında hiçbir iş bilgisi (fiyat, ürün adı, yazar,
        // değerlendirme vb.) uydurmaması açıkça söyleniyor.
        $schemaInstruction = $schemaContext !== null
            ? 'Bilinen gerçek site verisi - SADECE bunları kullan, başka hiçbir bilgi (fiyat, ürün adı, yazar, '
                . 'değerlendirme, tarih vb.) uydurma: URL=' . $schemaContext['url']
                . ($schemaContext['site_name'] !== '' ? ', site adı/title=' . $schemaContext['site_name'] : '')
                . ($schemaContext['description'] !== '' ? ', açıklama=' . $schemaContext['description'] : '')
                . '. example alanına bu gerçek verilerle dolu, geçerli bir Organization veya WebSite türünde '
                . '<script type="application/ld+json"> bloğu üret. Bilinmeyen hiçbir alanı ekleme. '
                . 'steps alanında "tür belirleyin" veya "kodu hazırlayın" gibi zaten example alanında yapılmış '
                . 'işleri kullanıcıya tekrar görev olarak verme - bunun yerine sadece kullanıcının GERÇEKTEN '
                . 'yapması gereken 2 adımı yaz: (1) example alanındaki kodu sayfanın <head> veya <body> kısmına '
                . 'ekle, (2) eklenen kodu Google Zengin Sonuç Testi ile doğrula.'
            : '';
        // Yetim sayfa bulgusunda "hangi sayfaya link eklensin" karari AI'a
        // birakilmiyor - ScoringEngine bunu URL yapisindan DETERMINISTIK
        // olarak (gercek, taranmis bir sayfayla eslesirse) buluyor. AI'in
        // isi sadece bu iki GERCEK sayfayi kullanarak dogal bir kod uretmek -
        // baska bir sayfa/URL onermesi acikca yasaklaniyor.
        $orphanInstruction = $orphanContext !== null
            ? 'Bilinen gerçek veri - SADECE bunları kullan, başka hiçbir sayfa/URL uydurma veya önerme: '
                . 'Yetim (iç linki olmayan) sayfa URL=' . $orphanContext['orphan_url']
                . ($orphanContext['orphan_title'] !== '' ? ', başlığı=' . $orphanContext['orphan_title'] : '')
                . '. Buna link verebilecek, gerçekten taranmış bir sayfa bulundu: '
                . $orphanContext['candidate_url']
                . ($orphanContext['candidate_title'] !== '' ? ' (başlığı=' . $orphanContext['candidate_title'] . ')' : '')
                . '. example alanına, bu kaynak sayfaya eklenebilecek şu formatta gerçek bir kod üret: '
                . '<a href="' . $orphanContext['orphan_url'] . '">[yetim sayfanın kendi başlığından türetilmiş '
                . 'kısa, doğal bir anchor text]</a>. steps alanında bu kodun ' . $orphanContext['candidate_url']
                . ' sayfasının içeriğine eklenmesi gerektiğini belirt; bulguda birden fazla yetim sayfa varsa '
                . 'diğerleri için de aynı yöntemin (en yakın üst kategori/listeleme sayfasını bulup link eklemek) '
                . 'tekrarlanması gerektiğini yaz.'
            : '';
        // Hicbir yetim sayfa icin deterministik bir kaynak sayfa bulunamadiysa
        // (ScoringEngine'in URL-yapisi eslesmesi basarisiz oldu) AI'a bunu
        // acikca soyluyoruz - aksi halde AI, trustedUrls listesindeki iki
        // yetim sayfayi birbirine baglamayi "cozum" gibi uydurabiliyor.
        $orphanNoCandidateInstruction = ($solutionType === 'internal_link_suggestion' && $orphanContext === null)
            ? 'Hiçbir yetim sayfa için güvenilir, gerçekten taranmış bir link kaynağı sayfa bulunamadı. '
                . 'example alanını KESİNLİKLE boş bırak; yetim sayfaları birbirine bağlama veya var olmayan bir '
                . 'kaynak sayfa uydurma. steps alanında sadece genel ama uygulanabilir yöntemler öner (ör. ana '
                . 'menüye, site haritasına veya ilgili kategori/blog sayfalarına elle bağlantı eklemek); herhangi '
                . 'bir URL veya sayfa adı uydurma.'
            : '';
        // H1 eksik bulgusunda AI'dan artik H1 METNI URETMESI istenmiyor -
        // her sayfanin onerisi PHP/JS tarafinda sayfanin gercek <title>
        // etiketinden birebir turetilip ayri bir listede gosteriliyor. AI'in
        // tek isi bu listeyi (asagida verilen gercek sayfa/title ciftleri)
        // kalite acisindan gozden gecirip warning alaninda uyari yazmak -
        // yeni bir baslik/metin uretmiyor, example'i her zaman bos birakiyor.
        $h1ReviewPairs = [];
        if ($solutionType === 'h1_suggestion' && is_array($finding['items'] ?? null)) {
            foreach ($finding['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemUrl = trim((string) ($item['url'] ?? ''));
                if ($itemUrl === '') {
                    continue;
                }
                $itemTitle = trim((string) ($item['title'] ?? ''));
                $h1ReviewPairs[] = $itemUrl . ' -> ' . ($itemTitle !== '' ? '"' . $itemTitle . '"' : '(title yok)');
            }
        }
        $h1ReviewInstruction = $solutionType === 'h1_suggestion'
            ? 'Bu bulguda her sayfa icin H1 onerisi zaten sayfanin gercek <title> etiketinden birebir turetildi ve '
                . 'kullaniciya ayri bir listede gosteriliyor - SEN YENI BIR H1/BASLIK METNI URETME, example alanini '
                . 'KESİNLİKLE boş bırak. İncelenecek gerçek sayfa/title çiftleri: '
                . ($h1ReviewPairs !== [] ? implode('; ', $h1ReviewPairs) : 'veri yok') . '. '
                . 'warning alanında SADECE bu listedeki gerçek verilere dayanarak, varsa şu sorunları belirt: '
                . 'birden fazla sayfa aynı veya çok benzer title\'a sahipse (H1\'ler de çakışır), title sadece '
                . 'marka/site adından ibaretse (sayfaya özgü değilse), veya title\'i olmayan sayfa varsa. Sorun '
                . 'yoksa kısa bir onay notu yaz. steps alanında önerilen H1\'leri uygulamadan önce sayfa '
                . 'içeriğiyle uyumlu olduklarını elle kontrol etmeyi ve title\'i olmayan sayfalar için H1\'i '
                . 'elle yazmayı belirt.'
            : '';

        // GENEL DURUM: yukarıdaki özel-durum context'lerinin (schema/orphan/
        // duplicate/h1/deterministicAsset) HİÇBİRİ eşleşmiyorsa, AI'a şimdiye kadar
        // sadece finding'in URL listesi (trustedUrls) gidiyordu - sayfa başına gerçek
        // durum bilgisi (ör. skipped_level bulgusunda hangi başlık seviyesinin
        // atlandığı, items içindeki 'status' alanında) prompt'a hiç yansıtılmıyordu.
        // Bu boşluk, AI'in finding.detail alanındaki GENEL/İLLÜSTRATİF örneği (ör.
        // "H1'den sonra doğrudan H3") GERÇEK bir sayfaya (trustedUrls'ten seçilen)
        // atfedip uydurma bir tespit gibi sunmasına yol açtı - gerçekten yaşandı.
        // Fix: varsa gerçek item durumlarını (url + status) açıkça veriyoruz ve AI'a
        // bunların dışında hiçbir sayfaya spesifik teknik detay atfetmemesini söylüyoruz.
        $hasDedicatedContext = $deterministicAsset || $schemaContext !== null || $orphanContext !== null
            || self::isDuplicateContentFinding($title, $detail) || $solutionType === 'h1_suggestion';
        $itemStatusPairs = [];
        if (!$hasDedicatedContext && is_array($finding['items'] ?? null)) {
            foreach ($finding['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemUrl = trim((string) ($item['url'] ?? ''));
                if ($itemUrl === '' || !in_array($itemUrl, $trustedUrls, true)) {
                    continue;
                }
                $itemStatus = trim((string) ($item['status'] ?? ''));
                $itemStatusPairs[] = $itemStatus !== '' ? ($itemUrl . ' -> ' . $itemStatus) : $itemUrl;
            }
        }
        $itemsInstruction = ($itemStatusPairs !== [])
            ? 'Bu bulgunun etkilediği gerçek sayfalar ve her biri için gerçek, doğrulanmış durum bilgisi: '
                . implode('; ', $itemStatusPairs) . '. example veya steps içinde belirli bir sayfaya özel '
                . 'teknik detay (ör. hangi başlık seviyesinin atlandığı, hangi linkin kırık olduğu) '
                . 'yazacaksan SADECE bu listede o sayfa için verilen gerçek durumu kullan - bulgunun genel '
                . 'açıklamasındaki illüstratif "örneğin" ifadesini gerçek bir sayfaya ait kesin bilgiymiş '
                . 'gibi sunma. Listede olmayan bir detayı asla uydurma; emin değilsen sadece genel ama '
                . 'uygulanabilir tavsiye ver.'
            : '';

        $exampleUrlRule = "example yalnızca faydalıysa ve verilen gerçek URL'lerden birini içeriyorsa dolu olsun; hayali URL veya placeholder üretme. ";

        return "BULGU:\nBaşlık: {$title}\nAyrıntı: {$detail}\n"
            . 'Mevcut düzeltme önerisi: ' . (string) ($finding['how_to_fix'] ?? '') . "\n"
            . 'Önem derecesi: ' . (string) ($finding['severity'] ?? '') . "\n"
            . 'Güven seviyesi: ' . (string) ($finding['confidence'] ?? '') . "\n"
            . 'Çözüm türü: ' . $solutionType . "\n"
            . $trustedUrlContext . "\n" . $assetInstruction . "\n" . $duplicateContentInstruction . "\n" . $canonicalGroupInstruction . "\n" . $schemaInstruction . "\n" . $orphanInstruction . "\n" . $orphanNoCandidateInstruction . "\n" . $h1ReviewInstruction . "\n" . $itemsInstruction . "\n\n"
            . "summary kısa ve bulguya özel olsun. steps en az 2 farklı uygulanabilir adım içersin. "
            . $exampleUrlRule
            . "warning kısa ve bulguya özel bir teknik kontrol uyarısı olsun. "
            . 'Sadece summary, steps, example ve warning alanlarından oluşan JSON nesnesi döndür. JSON dışında açıklama, markdown veya kod çiti yazma.';
    }

    private function buildPayload(string $prompt, bool $strictRetry): array
    {
        if ($strictRetry) {
            $prompt .= "\nÖNCEKİ YANIT GEÇERLİ JSON DEĞİLDİ. Yalnızca tek bir geçerli JSON nesnesi döndür. "
                . 'İlk karakter {, son karakter } olsun. Markdown, kod çiti, önsöz veya sonsöz ekleme.';
        }

        return [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'systemInstruction' => ['parts' => [['text' => 'Sen kıdemli bir teknik SEO danışmanısın. Yalnızca verilen JSON şemasına uyan ham JSON üret; açıklama, markdown ve kod bloğu üretme.']]],
            'generationConfig' => [
                'temperature' => $strictRetry ? 0.0 : 0.1,
                'maxOutputTokens' => 1024,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'summary' => ['type' => 'STRING'],
                        'steps' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'minItems' => 2],
                        'example' => ['type' => 'STRING'],
                        'warning' => ['type' => 'STRING'],
                    ],
                    'required' => ['summary', 'steps', 'example', 'warning'],
                ],
            ],
        ];
    }

    private function requestGemini(string $apiKey, string $model, array $payload): array
    {
        if ($this->transport !== null) {
            $result = ($this->transport)($payload);
            if (!is_array($result)) {
                throw new GeminiSolutionException('Gemini geçersiz bir upstream yanıtı döndürdü.', 'GEMINI_INVALID_RESPONSE', 502);
            }
            return $result;
        }

        $ch = curl_init(self::ENDPOINT . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey));
        if ($ch === false) throw new GeminiSolutionException('Gemini bağlantısı başlatılamadı.');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            error_log('[Technical AI] Gemini cURL hatası; errno=' . curl_errno($ch));
            throw new GeminiSolutionException('Gemini bağlantısı kurulamadı.', 'GEMINI_SERVICE_UNAVAILABLE', 503);
        }
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $result = json_decode((string) $response, true);
        if (!is_array($result)) {
            error_log('[Technical AI] Gemini HTTP JSON hatası; jsonError=' . json_last_error_msg() . ', responseLength=' . strlen((string) $response));
            throw new GeminiSolutionException('Gemini geçersiz bir upstream yanıtı döndürdü.', 'GEMINI_INVALID_RESPONSE', 502);
        }
        if ($httpCode < 200 || $httpCode >= 300 || isset($result['error'])) {
            $apiStatus = is_string($result['error']['status'] ?? null) ? $result['error']['status'] : 'UNKNOWN';
            error_log('[Technical AI] Gemini API hatası; HTTP=' . $httpCode . ', status=' . $apiStatus);
            throw self::classifyUpstreamError($httpCode, $apiStatus);
        }
        return $result;
    }

    private static function classifyUpstreamError(int $httpCode, string $apiStatus): GeminiSolutionException
    {
        if ($httpCode === 429 || $apiStatus === 'RESOURCE_EXHAUSTED') {
            return new GeminiSolutionException('Gemini istek sınırına ulaşıldı.', 'GEMINI_RATE_LIMITED', 429);
        }
        if ($httpCode >= 500 || in_array($apiStatus, ['UNAVAILABLE', 'INTERNAL', 'DEADLINE_EXCEEDED'], true)) {
            return new GeminiSolutionException('Gemini servisi geçici olarak kullanılamıyor.', 'GEMINI_SERVICE_UNAVAILABLE', 503);
        }
        return new GeminiSolutionException('Gemini API isteği başarısız oldu.', 'GEMINI_API_ERROR', 502);
    }

    /** @return array{0:string,1:int,2:list<string>} */
    private function extractCandidateText(array $result): array
    {
        $candidates = is_array($result['candidates'] ?? null) ? $result['candidates'] : [];
        if ($candidates === []) {
            $reason = (string) ($result['promptFeedback']['blockReason'] ?? 'UNKNOWN');
            error_log('[Technical AI] Gemini aday döndürmedi; candidates=0, finishReason=, blockReason=' . $reason);
            throw new GeminiSolutionException(
                'AI çözümü üretilemedi.',
                $reason !== 'UNKNOWN' ? 'GEMINI_SAFETY_BLOCK' : 'GEMINI_EMPTY_CANDIDATES',
                502
            );
        }

        $texts = [];
        $finishReasons = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) continue;
            if (isset($candidate['finishReason'])) $finishReasons[] = (string) $candidate['finishReason'];
            $parts = $candidate['content']['parts'] ?? [];
            if (!is_array($parts)) continue;
            foreach ($parts as $part) {
                if (is_array($part) && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                    $texts[] = trim($part['text']);
                }
            }
        }
        $text = trim(implode("\n", $texts));
        if ($text === '') {
            $blocked = array_intersect(['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT'], $finishReasons) !== [];
            throw new GeminiSolutionException('Gemini boş bir çözüm döndürdü.', $blocked ? 'GEMINI_SAFETY_BLOCK' : 'GEMINI_EMPTY_RESPONSE', 502);
        }
        return [$text, count($candidates), $finishReasons];
    }

    private static function decodeStructuredText(string $text, string &$error): ?array
    {
        $clean = preg_replace('/^(?:\xEF\xBB\xBF|\x{FEFF})+/u', '', $text) ?? $text;
        $clean = trim($clean);
        $withoutFences = preg_replace('/```(?:json)?\s*|```/iu', '', $clean) ?? $clean;
        $candidates = [trim($withoutFences)];
        foreach (self::extractBalancedJsonObjects($withoutFences) as $jsonObject) $candidates[] = $jsonObject;

        $lastError = 'Syntax error';
        foreach (array_unique($candidates) as $candidate) {
            $decoded = json_decode($candidate, true);
            $lastError = json_last_error_msg();
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $error = 'No error';
                return $decoded;
            }
        }
        $error = $lastError;
        return null;
    }

    /** @return list<string> */
    private static function extractBalancedJsonObjects(string $text): array
    {
        $objects = [];
        $length = strlen($text);
        for ($start = 0; $start < $length; $start++) {
            if ($text[$start] !== '{') continue;
            $depth = 0;
            $inString = false;
            $escaped = false;
            for ($i = $start; $i < $length; $i++) {
                $char = $text[$i];
                if ($inString) {
                    if ($escaped) $escaped = false;
                    elseif ($char === '\\') $escaped = true;
                    elseif ($char === '"') $inString = false;
                    continue;
                }
                if ($char === '"') $inString = true;
                elseif ($char === '{') $depth++;
                elseif ($char === '}' && --$depth === 0) {
                    $objects[] = substr($text, $start, $i - $start + 1);
                    $start = $i;
                    break;
                }
            }
        }
        return $objects;
    }

    /** @param list<string> $trustedUrls */
    private function normalizeSolution(array $data, array $trustedUrls, bool $forceEmptyExample, bool $skipUrlSafetyCheck = false): array
    {
        $summary = trim((string) ($data['summary'] ?? $data['explanation'] ?? $data['content'] ?? ''));
        $rawSteps = $data['steps'] ?? $data['implementation_steps'] ?? [];
        $steps = is_array($rawSteps)
            ? array_values(array_unique(array_filter(array_map(static fn ($step): string => is_scalar($step) ? trim((string) $step) : '', $rawSteps))))
            : [];
        $example = trim((string) ($data['example'] ?? $data['code_example'] ?? ''));
        $warning = trim((string) ($data['warning'] ?? $data['risk_note'] ?? ''));
        if ($example !== '' && !$skipUrlSafetyCheck && !self::isSafeExample($example, $trustedUrls)) $example = '';
        if ($forceEmptyExample) $example = '';
        if ($summary === '' || count($steps) < 2 || $warning === '') {
            throw new GeminiSolutionException('Gemini çözümü eksik alanlar içeriyor.', 'GEMINI_INVALID_RESPONSE', 502);
        }
        return ['summary' => $summary, 'steps' => $steps, 'example' => $example, 'warning' => $warning];
    }

    /** @return list<string> */
    /**
     * JSON-LD bulgusu icin AI'a verilecek, TAMAMEN gercek/bilinen site
     * verisini cikarir (site adi, URL, aciklama). Bu disinda hicbir is
     * bilgisi (fiyat, urun, yazar vb.) AI'a verilmez - cunku crawl'da yok
     * ve AI'in uydurmasi gerekirdi.
     *
     * @param list<string> $trustedUrls
     * @return array{url:string, site_name:string, description:string}|null
     */
    private static function extractSchemaContext(mixed $items, array $trustedUrls): ?array
    {
        if (!is_array($items) || $items === [] || $trustedUrls === []) {
            return null;
        }
        $item = $items[0];
        if (!is_array($item)) {
            return null;
        }
        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '' || !in_array($url, $trustedUrls, true)) {
            return null;
        }
        $siteName = trim((string) ($item['title'] ?? ''));
        $description = trim((string) ($item['meta_description'] ?? ''));
        if ($siteName === '' && $description === '') {
            return null;
        }
        return ['url' => $url, 'site_name' => $siteName, 'description' => $description];
    }

    /**
     * Yetim sayfa bulgusu icin AI'a verilecek TEK bir gercek ornek cikarir:
     * yetim sayfanin kendi URL'i/basligi + ScoringEngine'in URL yapisindan
     * DETERMINISTIK olarak bulup dogruladigi gercek link-kaynagi sayfa.
     * Boyle bir aday hicbir item'da yoksa (hepsi dusuk guven) null doner -
     * AI o zaman ornek/hedef uretmez, sadece genel tavsiye verir.
     *
     * @param list<string> $trustedUrls
     * @return array{orphan_url:string, orphan_title:string, candidate_url:string, candidate_title:string}|null
     */
    private static function extractOrphanContext(mixed $items, array $trustedUrls): ?array
    {
        if (!is_array($items)) {
            return null;
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = trim((string) ($item['url'] ?? ''));
            $candidateUrl = trim((string) ($item['candidate_url'] ?? ''));
            if ($url === '' || $candidateUrl === '' || !in_array($url, $trustedUrls, true)) {
                continue;
            }
            return [
                'orphan_url' => $url,
                'orphan_title' => trim((string) ($item['title'] ?? '')),
                'candidate_url' => $candidateUrl,
                'candidate_title' => trim((string) ($item['candidate_title'] ?? '')),
            ];
        }
        return null;
    }

    private static function extractTrustedUrls(mixed $items): array
    {
        if (!is_array($items)) return [];
        $urls = [];
        foreach ($items as $item) {
            $url = is_array($item) ? trim((string) ($item['url'] ?? '')) : '';
            if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) continue;
            if (!in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) continue;
            $urls[] = $url;
            if (count($urls) === 5) break;
        }
        return array_values(array_unique($urls));
    }

    /** @param list<string> $trustedUrls */
    private static function isSafeExample(string $example, array $trustedUrls): bool
    {
        if ($trustedUrls === []) return false;
        if (preg_match('~(?:www\.)?(?:ornek|örnek|example|alanadi|domain)\.(?:com|net|org)|/sayfa-adi\b~iu', $example) === 1) return false;
        foreach ($trustedUrls as $url) if (str_contains($example, $url)) return true;
        return false;
    }

    private static function isDuplicateContentFinding(string $title, string $detail): bool
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($title . ' ' . $detail, 'UTF-8') : strtolower($title . ' ' . $detail);
        $isDuplicate = str_contains($text, 'duplicate') || str_contains($text, 'yinelenen');
        $isTitleOrMeta = str_contains($text, 'title') || str_contains($text, 'başlık')
            || str_contains($text, 'meta description') || str_contains($text, 'açıklama');
        return $isDuplicate && $isTitleOrMeta;
    }
}

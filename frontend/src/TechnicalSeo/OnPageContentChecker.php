<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * Sitede taranmış TÜM sayfaların (SiteStructureAnalyzer::crawl() çıktısındaki
 * 'pages' listesi) arasındaki tekrar eden / yapısal içerik sorunlarını bulur:
 * yinelenen <title>, yinelenen meta description, ve H1-H6 başlık hiyerarşisi
 * sorunları (eksik H1, birden fazla H1, seviye atlaması).
 *
 * OnPageIndexabilityChecker'dan FARKI: o TEK bir sayfanın ham HTML'ine bakar
 * (ör. ana sayfa canonical/noindex kontrolü). Bu sınıf ise TÜM taranan
 * sayfaların ÖZETlenmiş verisini (tam HTML değil - bellek dostu kalması için
 * crawl sırasında çıkarılan title/meta_description/heading_structure) alıp
 * sayfalar ARASI karşılaştırma yapar.
 *
 * CANONICAL-FARKINDA TEKRAR TESPİTİ (Aşama 1): Aynı title/description'ı
 * paylaşan bir sayfa grubu, faceted/filtrelenmiş navigasyon (ör.
 * ?sektor=x, ?sayfa=2) gibi MEŞRU nedenlerle oluşabilir - bu durumda doğru
 * SEO çözümü "her varyasyona benzersiz title yaz" değil, "canonical ile
 * birincil sayfaya işaret et"tir. Bu yüzden her grup, sayfaların KENDİ
 * canonical beyanlarına bakılarak 'resolved' (grubun büyük çoğunluğu aynı,
 * geçerli, ulaşılabilir ve kendi de başka yere yönlendirmeyen/zincirlemeyen
 * TEK bir hedefe canonical veriyor) veya 'unresolved' (hâlâ gerçek bir
 * kopya-içerik sorunu ya da canonical sinyali eksik/çelişkili/doğrulanamaz)
 * olarak sınıflandırılır. Sadece crawl sırasında toplanan veriyle çalışır -
 * hiçbir zaman canonical hedefinin İÇERİK düzeyinde gerçekten doğru sayfa
 * olup olmadığını doğrulayamaz (bu her zaman kapsam dışıdır); sadece hedefin
 * ulaşılabilir (2xx), yönlendirmeyen/zincirlemeyen/döngüsüz/noindex-olmayan
 * ve grup içi çoğunluğun üzerinde anlaştığı bir URL olup olmadığını kontrol
 * eder.
 *
 * CANLI DOĞRULAMA (Aşama 2, opsiyonel): Constructor'a bir Crawler verilirse,
 * crawl sırasında GÖRÜLMEMİŞ (sayfa/derinlik/zaman limitine takılmış
 * olabilecek) canonical hedefleri CANLI olarak getirilip aynı kriterlerle
 * (durum kodu, yönlendirme, noindex, kendi canonical'inin yapısal sağlığı)
 * değerlendirilir - böylece 'unverified' (doğrulanamadı) ile 'broken_target'
 * (gerçekten bozuk olduğu KANITLANDI) ayrımı netleşir. Crawler verilmezse
 * (varsayılan: null) sınıf tamamen Aşama 1 davranışında kalır, hiçbir ek ağ
 * isteği yapmaz - geriye dönük uyumluluk bilerek korunmuştur.
 */
final class OnPageContentChecker
{
    /** Bir canonical zincirinde takip edilecek azami adım sayısı - sonsuz
     *  döngüye karşı güvenlik sınırı (gerçek döngüler zaten 'visited'
     *  kontrolüyle daha erken yakalanır, bu sadece ek bir üst sınırdır). */
    private const MAX_CANONICAL_HOPS = 10;

    /** Aşama 2 (canlı doğrulama) - null ise Aşama 1 davranışı: crawl
     *  dışındaki hedefler sadece 'unverified' (doğrulanamadı) olarak
     *  işaretlenir, hiçbir ağ isteği YAPILMAZ. Crawler verilirse, crawl
     *  dışında kalan canonical hedefleri (crawl sayfa limiti yüzünden
     *  taranmamış olabilirler) canlı olarak getirip gerçekten bozuk mu,
     *  yönlendiriyor mu, noindex mi, yoksa gerçekten geçerli mi diye
     *  KESİNLEŞTİRİR. */
    private ?Crawler $crawler;
    private OnPageIndexabilityChecker $indexabilityChecker;
    /** Aşama 2'de canlı getirilen hedefleri, ayni denetim çalışması
     *  içinde (findDuplicateTitles + findDuplicateMetaDescriptions ikisi
     *  de dahil) TEKRAR TEKRAR aynı URL'i ağdan çekmemek için önbelleğe
     *  alır. null değer = denendi ama gerçekten ulaşılamadı (SSRF engeli,
     *  zaman aşımı, DNS hatası vb.) - tekrar denenmez. */
    private array $liveTargetCache = [];
    private ?SiteStructureAnalyzer $liveAnalyzer = null;

    public function __construct(?Crawler $crawler = null)
    {
        $this->crawler = $crawler;
        $this->indexabilityChecker = new OnPageIndexabilityChecker();
    }

    /**
     * Aynı (normalize edilmiş - baş/son boşluk kırpılmış, küçük harfe
     * çevrilmiş) title etiketini paylaşan sayfa gruplarını bulur. Boş/eksik
     * title'lar bu karşılaştırmaya DAHİL EDİLMEZ - "title hiç yok" ayrı bir
     * sorun, burada sadece "2+ sayfa AYNI title'ı paylaşıyor" aranıyor.
     *
     * @param list<array{url:string, title?:string|null, status_code?:int, final_url?:string, canonical?:string|null, canonical_issue?:string|null}> $pages
     * @return list<array{value:string, urls:list<string>, status:string, primary_target:?string, resolved_count:int, total_count:int, unresolved_details:list<array{url:string, reason:string}>}>
     */
    public function findDuplicateTitles(array $pages): array
    {
        return $this->findDuplicates($pages, 'title');
    }

    /**
     * @param list<array{url:string, meta_description?:string|null, status_code?:int, final_url?:string, canonical?:string|null, canonical_issue?:string|null}> $pages
     * @return list<array{value:string, urls:list<string>, status:string, primary_target:?string, resolved_count:int, total_count:int, unresolved_details:list<array{url:string, reason:string}>}>
     */
    public function findDuplicateMetaDescriptions(array $pages): array
    {
        return $this->findDuplicates($pages, 'meta_description');
    }

    /**
     * Sitede canonical etiketinin KENDİSİNDE yapısal bir sorun olan
     * sayfaları toplar (SiteStructureAnalyzer::extractCanonicalInfo()
     * tarafından tespit edilir): birden fazla canonical etiketi, boş href,
     * veya http(s) dışı bir protokol. Bunlar herhangi bir duplicate grubuna
     * dahil olsun olmasın HER ZAMAN ayrı bir bulgudur - çünkü sayfanın
     * canonical sinyali arama motorları için zaten belirsiz/geçersizdir.
     *
     * @param list<array{url:string, canonical_issue?:string|null}> $pages
     * @return list<array{url:string, issue:string}>
     */
    public function findCanonicalIssues(array $pages): array
    {
        $issues = [];
        foreach ($pages as $page) {
            $issue = $page['canonical_issue'] ?? null;
            if (is_string($issue) && $issue !== '') {
                $issues[] = ['url' => $page['url'], 'issue' => $issue];
            }
        }
        return $issues;
    }

    /**
     * @param list<array<string,mixed>> $pages
     * @return list<array{value:string, urls:list<string>, status:string, primary_target:?string, resolved_count:int, total_count:int, unresolved_details:list<array{url:string, reason:string}>}>
     */
    private function findDuplicates(array $pages, string $field): array
    {
        $groups = [];
        foreach ($pages as $page) {
            $value = $page[$field] ?? null;
            if (!is_string($value)) {
                continue;
            }
            $normalized = mb_strtolower(trim($value));
            if ($normalized === '') {
                continue;
            }
            $groups[$normalized]['value'] ??= trim($value);
            $groups[$normalized]['urls'][] = $page['url'];
        }

        $rawDuplicates = [];
        foreach ($groups as $group) {
            if (count($group['urls']) > 1) {
                $rawDuplicates[] = $group;
            }
        }

        if (empty($rawDuplicates)) {
            return [];
        }

        $byUrl = $this->buildCanonicalIndex($pages);
        $siteHost = '';
        foreach ($pages as $page) {
            $h = parse_url((string) ($page['url'] ?? ''), PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                $siteHost = mb_strtolower($h);
                break;
            }
        }

        $result = [];
        foreach ($rawDuplicates as $group) {
            $classification = $this->classifyGroup($group['urls'], $byUrl, $siteHost);
            $result[] = [
                'value' => $group['value'],
                'urls' => $group['urls'],
                'status' => $classification['status'],
                'primary_target' => $classification['primary_target'],
                'resolved_count' => $classification['resolved_count'],
                'total_count' => $classification['total_count'],
                'unresolved_details' => $classification['unresolved_details'],
            ];
        }

        return $result;
    }

    /**
     * Bir URL'i karşılaştırma için normalize eder: göreli olabilecek href
     * zaten SiteStructureAnalyzer::resolveUrl() ile mutlak hale getirilmiş
     * olarak gelir; burada sadece host küçük harfe çevrilir, fragment
     * atılır, varsayılan portlar (http:80, https:443) temizlenir, ve sondaki
     * '/' (kök yol hariç) kırpılır. Query string'i KASITLI OLARAK KORUR -
     * ör. ?id=42 gibi anlamlı bir parametreyi atmak iki farklı sayfayı
     * yanlışlıkla "aynı" gösterebilir.
     */
    private function normalizeUrlForCompare(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return mb_strtolower($url);
        }

        $scheme = mb_strtolower($parts['scheme'] ?? 'https');
        $host = mb_strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        if ($port !== null && (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $port = null;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $normalized = $scheme . '://' . $host . ($port !== null ? ':' . $port : '') . $path;
        if (isset($parts['query']) && $parts['query'] !== '') {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    /**
     * Taranan tüm sayfaları normalize edilmiş URL'lerine göre indeksler -
     * her canonical hedefinin durumunu (crawl içinde var mı, 2xx mi,
     * kendisi yönlendiriyor mu) O(1) sorgulayabilmek için.
     *
     * @param list<array<string,mixed>> $pages
     * @return array<string, array{status_code:int, final_url:string, final_url_normalized:string, canonical:?string, canonical_normalized:?string, canonical_issue:?string, noindex:bool}>
     */
    private function buildCanonicalIndex(array $pages): array
    {
        $byUrl = [];
        foreach ($pages as $page) {
            $url = (string) ($page['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $normalized = $this->normalizeUrlForCompare($url);
            $finalUrl = (string) ($page['final_url'] ?? $url);
            $canonical = $page['canonical'] ?? null;
            $byUrl[$normalized] = [
                'status_code' => (int) ($page['status_code'] ?? 0),
                'final_url' => $finalUrl,
                'final_url_normalized' => $this->normalizeUrlForCompare($finalUrl),
                'canonical' => is_string($canonical) ? $canonical : null,
                'canonical_normalized' => is_string($canonical) ? $this->normalizeUrlForCompare($canonical) : null,
                'canonical_issue' => $page['canonical_issue'] ?? null,
                // Asama 2: hedef sayfanin KENDISI noindex ise, ona canonical
                // vermek anlamsizdir - arama motoru zaten o hedefi indekslemez.
                'noindex' => ($page['noindex'] ?? false) === true,
            ];
        }
        return $byUrl;
    }

    /**
     * Bir canonical hedefinin durumunu, hedeften başlayarak (gerekirse
     * hedefin KENDİ canonical'ini de takip ederek) sınıflandırır:
     *  - 'ok'               : hedef biliniyor (crawl içinde YA DA Aşama 2'de
     *                        canlı doğrulanmış), 2xx, kendisi yönlendirmiyor,
     *                        noindex değil, kendi canonical'i de yapısal
     *                        olarak sağlam, kendi kendine canonical veriyor
     *                        (veya hiç canonical'i yok) - EK ADIM YOK (hops=0)
     *  - 'chain'            : hedef geçerli ama kendi canonical'i BAŞKA bir
     *                        (geçerli) URL'e işaret ediyor - 1+ ek adım var
     *  - 'loop'             : zincir daha önce ziyaret edilen bir URL'e geri
     *                        dönüyor (A -> B -> A)
     *  - 'broken_target'    : hedef 2xx değil (4xx/5xx) YA DA hedefin kendi
     *                        canonical etiketi yapısal olarak bozuk (çoklu/
     *                        boş href/geçersiz protokol - böyle bir hedefe
     *                        güvenilemez)
     *  - 'target_redirects' : hedefin kendi URL'i, final_url'inden farklı
     *                        (yani HTTP yönlendirmesiyle başka yere çıkıyor)
     *  - 'target_noindex'   : hedef sayfa noindex ile işaretli - arama
     *                        motoru bu sayfayı zaten indekslemeyecek, ona
     *                        canonical vermek anlamsız (Aşama 2)
     *  - 'not_in_crawl'     : hedef ne taranan sayfalar arasında bulunabildi
     *                        ne de (Crawler verilmişse) canlı olarak
     *                        ulaşılabildi - crawler yoksa (Aşama 1) ya da
     *                        canlı istek de başarısız olduysa (DNS/zaman
     *                        aşımı/SSRF engeli) buraya düşer; KESİN "kırık"
     *                        anlamına gelmez
     *
     * @param array<string, array<string,mixed>> $byUrl
     * @return array{status:string, final_target:?string}
     */
    private function walkCanonicalTarget(string $normalizedTarget, array $byUrl): array
    {
        $visited = [];
        $current = $normalizedTarget;
        $hops = 0;

        while (true) {
            if (isset($visited[$current])) {
                return ['status' => 'loop', 'final_target' => $current];
            }
            $visited[$current] = true;

            $entry = $byUrl[$current] ?? null;
            if ($entry === null) {
                // Crawl icinde yok - Asama 2 aktifse (Crawler verildiyse)
                // canli olarak getirmeyi dene; degilse (Asama 1) veya canli
                // istek de basarisiz olursa 'not_in_crawl' ile cik.
                $entry = $this->fetchLiveTarget($current);
                if ($entry === null) {
                    return ['status' => 'not_in_crawl', 'final_target' => $current];
                }
            }

            if (($entry['canonical_issue'] ?? null) !== null) {
                // Hedefin KENDI canonical etiketi yapisal olarak bozuk -
                // findCanonicalIssues() bunu (crawl icindeyse) ayrica da
                // raporlayacak, ama zincir acisindan da guvenilmez bir hedef.
                return ['status' => 'broken_target', 'final_target' => $current];
            }

            if (($entry['noindex'] ?? false) === true) {
                return ['status' => 'target_noindex', 'final_target' => $current];
            }

            if ($entry['status_code'] < 200 || $entry['status_code'] >= 300) {
                return ['status' => 'broken_target', 'final_target' => $current];
            }

            if ($entry['final_url_normalized'] !== $current) {
                return ['status' => 'target_redirects', 'final_target' => $entry['final_url_normalized']];
            }

            $ownCanonical = $entry['canonical_normalized'];
            if ($ownCanonical === null || $ownCanonical === $current) {
                return $hops === 0
                    ? ['status' => 'ok', 'final_target' => $current]
                    : ['status' => 'chain', 'final_target' => $current];
            }

            $hops++;
            if ($hops > self::MAX_CANONICAL_HOPS) {
                return ['status' => 'chain', 'final_target' => $current];
            }
            $current = $ownCanonical;
        }
    }

    /**
     * ASAMA 2: crawl disinda kalan bir canonical hedefini CANLI olarak
     * getirir (crawl sayfa/derinlik/zaman limitine takilip taranmamis
     * olabilir). $this->crawler null ise (Asama 1 modu) hep null doner ve
     * HICBIR ag istegi yapilmaz - geriye donuk uyumluluk icin varsayilan.
     *
     * Sonuc, ayni denetim calismasi icinde ($this->liveTargetCache) TEKRAR
     * TEKRAR aginan URL'ler icin onbelleklenir - findDuplicateTitles() ve
     * findDuplicateMetaDescriptions() ayni OnPageContentChecker ornegini
     * paylastigi icin (api/technical_seo_audit.php'de tek bir $contentChecker
     * kullanilir) bu onbellek her iki cagriyi da kapsar.
     *
     * DURUST SINIRLAMA: burada da SADECE teknik ulasilabilirlik/tutarlilik
     * dogrulanir (durum kodu, yonlendirme, noindex, kendi canonical'i) -
     * hedefin ICERIK olarak dogru sayfa olup olmadigi bu denetimin
     * KAPSAMI DISINDA kalmaya devam ediyor.
     *
     * @return array{status_code:int, final_url:string, final_url_normalized:string, canonical:?string, canonical_normalized:?string, canonical_issue:?string, noindex:bool}|null
     */
    private function fetchLiveTarget(string $normalizedUrl): ?array
    {
        if (array_key_exists($normalizedUrl, $this->liveTargetCache)) {
            return $this->liveTargetCache[$normalizedUrl];
        }

        if ($this->crawler === null) {
            return null;
        }

        $results = $this->crawler->fetchMultiple([$normalizedUrl], 4, false, true);
        $result = $results[$normalizedUrl] ?? null;

        if ($result === null || $result['error'] !== null) {
            $this->liveTargetCache[$normalizedUrl] = null;
            return null;
        }

        $statusCode = (int) ($result['status_code'] ?? 0);
        $finalUrl = (string) ($result['final_url'] ?? $normalizedUrl);
        $body = (string) ($result['body'] ?? '');
        $headers = is_array($result['headers'] ?? null) ? $result['headers'] : [];

        $canonicalInfo = ['value' => null, 'issue' => null];
        $noindex = false;
        if ($statusCode >= 200 && $statusCode < 300) {
            $canonicalInfo = $this->getLiveAnalyzer()->extractCanonicalInfo($body, $finalUrl);
            $noindexResult = $this->indexabilityChecker->checkNoindex($body, $headers);
            $noindex = $noindexResult['noindex'];
        }

        $entry = [
            'status_code' => $statusCode,
            'final_url' => $finalUrl,
            'final_url_normalized' => $this->normalizeUrlForCompare($finalUrl),
            'canonical' => $canonicalInfo['value'],
            'canonical_normalized' => $canonicalInfo['value'] !== null ? $this->normalizeUrlForCompare($canonicalInfo['value']) : null,
            'canonical_issue' => $canonicalInfo['issue'],
            'noindex' => $noindex,
        ];

        $this->liveTargetCache[$normalizedUrl] = $entry;
        return $entry;
    }

    /**
     * SiteStructureAnalyzer::extractCanonicalInfo() (artik public) mantigini
     * canli getirilen sayfalarda da AYNEN kullanabilmek icin tembel (lazy)
     * bir ornek olusturur - regex'i burada ikinci kez yazmiyoruz.
     */
    private function getLiveAnalyzer(): SiteStructureAnalyzer
    {
        if ($this->liveAnalyzer === null) {
            $this->liveAnalyzer = new SiteStructureAnalyzer($this->crawler);
        }
        return $this->liveAnalyzer;
    }

    /**
     * Bir title/meta_description tekrar grubunu, grup içindeki sayfaların
     * canonical beyanlarına bakarak 'resolved' ya da 'unresolved' olarak
     * sınıflandırır. Grup 'resolved' sayılabilmesi için:
     *  1) URL'lerin BÜYÜK ÇOĞUNLUĞU (>%50) aynı hedefe canonical veriyor,
     *  2) o hedef site ile AYNI DOMAIN'de (çapraz domain = düşük güven,
     *     otomatik hata değil - ayrı 'cross_domain' bulgusuna düşer),
     *  3) hedef biliniyor (crawl içinde ya da Aşama 2'de canlı doğrulanmış)
     *     ve 2xx dönüyor, noindex DEĞİL, kendi canonical'i yapısal olarak
     *     sağlam,
     *  4) hedefin kendisi başka bir yere yönlendirmiyor/zincirlenmiyor/
     *     döngü oluşturmuyor (yalnızca kendi kendine canonical veriyor ya
     *     da hiç canonical'i yok).
     * Bu koşullardan biri bile sağlanmazsa grup 'unresolved' kalır ve her
     * sayfa için AYRI bir sebep (reason) döner - ScoringEngine bunları
     * uygun ayrı bulgulara (yapısal hata / kırık-zincir-döngü / inceleme
     * gerekli) dağıtabilsin diye. İSTİSNA: zirvede birden fazla hedef AYNI
     * oy sayısını paylaşıyorsa ('tied_targets'), hangisinin "çoğunluk"
     * sayılacağı zaten anlamsız olduğundan tüm sayfalara TEK ve SİMETRİK
     * bir sebep verilir - "biri çoğunluk, diğerleri ona uymuyor" gibi
     * yanıltıcı bir asimetri kurulmaz (özellikle 2 sayfalık 1'e-1 gruplarda
     * önemli).
     *
     * @param list<string> $urls
     * @param array<string, array<string,mixed>> $byUrl
     * @return array{status:string, primary_target:?string, resolved_count:int, total_count:int, unresolved_details:list<array{url:string, reason:string}>}
     */
    private function classifyGroup(array $urls, array $byUrl, string $siteHost): array
    {
        $votes = [];
        $perUrlTarget = [];

        foreach ($urls as $url) {
            $norm = $this->normalizeUrlForCompare($url);
            $entry = $byUrl[$norm] ?? null;

            if ($entry === null || $entry['canonical_issue'] !== null || $entry['canonical_normalized'] === null) {
                $perUrlTarget[$url] = null;
                continue;
            }

            $target = $entry['canonical_normalized'];
            $perUrlTarget[$url] = $target;
            $votes[$target] = ($votes[$target] ?? 0) + 1;
        }

        $total = count($urls);

        if (empty($votes)) {
            $details = [];
            foreach ($urls as $url) {
                $norm = $this->normalizeUrlForCompare($url);
                $entry = $byUrl[$norm] ?? null;
                $reason = ($entry !== null && $entry['canonical_issue'] !== null) ? 'structural_issue' : 'self_or_missing';
                $details[] = ['url' => $url, 'reason' => $reason];
            }
            return [
                'status' => 'unresolved',
                'primary_target' => null,
                'resolved_count' => 0,
                'total_count' => $total,
                'unresolved_details' => $details,
            ];
        }

        arsort($votes);
        $primaryTarget = (string) array_key_first($votes);
        $primaryVotes = $votes[$primaryTarget];
        $isMajority = $primaryVotes > ($total / 2);

        // TAM BERABERLIK tespiti: birden fazla hedef ayni (en yuksek) oy
        // sayisini paylasiyorsa, array_key_first() bunlardan SADECE EKLENME
        // SIRASINA gore birini "primary" secer - bu rastgele secimi "cogunluk
        // budur, digerleri ona uymuyor" diye asimetrik anlatmak yaniltici
        // olur (ozellikle 2 sayfalik 1'e-1 gruplarda). Boyle bir durumda
        // butun taraflara AYNI, simetrik sebep ('tied_targets') veriliyor.
        $maxVotes = max($votes);
        $tiedTargetCount = count(array_filter($votes, static fn (int $v): bool => $v === $maxVotes));
        $isTie = $tiedTargetCount > 1;

        // ONEMLI: port'u DAHIL ETMEDEN sadece host karsilastiriyoruz - $siteHost
        // zaten parse_url(..., PHP_URL_HOST) ile port'suz hesaplaniyor (asagida).
        // Onceden burada "https://host:port/..." bicimini yakalayan bir regex
        // kullaniliyordu ve port'u DA host'un parcasi sayiyordu - bu, standart
        // disi bir portta calisan (ama ayni domain'deki) bir hedefi YANLISLIKLA
        // 'cross_domain' olarak isaretliyordu.
        $targetHostRaw = parse_url($primaryTarget, PHP_URL_HOST);
        $targetHost = is_string($targetHostRaw) && $targetHostRaw !== '' ? mb_strtolower($targetHostRaw) : null;
        $crossDomain = $targetHost !== null && $siteHost !== '' && $targetHost !== $siteHost;

        $walk = $this->walkCanonicalTarget($primaryTarget, $byUrl);
        $resolved = $isMajority && !$crossDomain && $walk['status'] === 'ok';

        $unresolvedDetails = [];
        if (!$resolved) {
            foreach ($urls as $url) {
                $target = $perUrlTarget[$url] ?? null;

                if ($target === null) {
                    $norm = $this->normalizeUrlForCompare($url);
                    $entry = $byUrl[$norm] ?? null;
                    $reason = ($entry !== null && $entry['canonical_issue'] !== null) ? 'structural_issue' : 'self_or_missing';
                    $unresolvedDetails[] = ['url' => $url, 'reason' => $reason];
                    continue;
                }

                if ($isTie) {
                    // Zirvede net bir "kazanan" yok - hangi hedefe oy verdigi
                    // fark etmeksizin butun sayfalar ayni (simetrik) sebeple
                    // isaretlenir; kimin "cogunluk" kimin "cogunluga uymayan"
                    // oldugunu iddia etmek burada anlamsiz.
                    $unresolvedDetails[] = ['url' => $url, 'reason' => 'tied_targets'];
                    continue;
                }

                if ($target !== $primaryTarget) {
                    $unresolvedDetails[] = ['url' => $url, 'reason' => 'conflicting_target'];
                    continue;
                }

                // ONEMLI SIRALAMA: cogunluk yoksa ('no_majority') bu, en temel/kok
                // sebep - kazanan aday hedefin kendisi ne kadar saglam olursa
                // olsun, sayfalar zaten ortak bir hedefte ANLASAMAMIS demektir.
                // Bu yuzden hedefin kendi durumuyla (capraz domain/bozuk/zincir/
                // dogrulanamayan) ilgili sebeplerden ONCE kontrol ediliyor.
                $reason = match (true) {
                    !$isMajority => 'no_majority',
                    $crossDomain => 'cross_domain',
                    $walk['status'] === 'not_in_crawl' => 'unverified',
                    $walk['status'] === 'broken_target' => 'broken_target',
                    $walk['status'] === 'target_redirects' => 'target_redirects',
                    $walk['status'] === 'target_noindex' => 'target_noindex',
                    $walk['status'] === 'chain' => 'chain',
                    $walk['status'] === 'loop' => 'loop',
                    default => 'unresolved',
                };
                $unresolvedDetails[] = ['url' => $url, 'reason' => $reason];
            }
        }

        return [
            'status' => $resolved ? 'resolved' : 'unresolved',
            'primary_target' => $primaryTarget,
            'resolved_count' => $primaryVotes,
            'total_count' => $total,
            'unresolved_details' => $unresolvedDetails,
        ];
    }

    /**
     * H1-H6 başlık hiyerarşisini denetler: eksik H1, birden fazla H1, ve
     * seviye atlaması (ör. H1'den sonra doğrudan H3 - H2 atlanmış).
     *
     * Sadece BAŞARIYLA çekilmiş (2xx) sayfalar değerlendirilir - hata/
     * yönlendirme kayıtlarında zaten içerik çıkarılmamıştır (heading_structure
     * boş dizidir), bunları "H1 eksik" diye işaretlemek yanıltıcı olurdu.
     *
     * @param list<array{url:string, status_code?:int, heading_structure?:list<int>}> $pages
     * @return array{
     *   missing_h1: list<string>,
     *   multiple_h1: list<array{url:string, count:int}>,
     *   skipped_level: list<array{url:string, detail:string}>
     * }
     */
    public function analyzeHeadingHierarchy(array $pages): array
    {
        $missingH1 = [];
        $multipleH1 = [];
        $skippedLevel = [];

        foreach ($pages as $page) {
            $statusCode = $page['status_code'] ?? 0;
            if ($statusCode < 200 || $statusCode >= 300) {
                continue; // hata/yönlendirme sayfaları - içerik analizi anlamsız
            }

            $structure = $page['heading_structure'] ?? [];
            $url = $page['url'];
            $h1Count = count(array_filter($structure, static fn (int $level): bool => $level === 1));

            if ($h1Count === 0) {
                $missingH1[] = $url;
            } elseif ($h1Count > 1) {
                $multipleH1[] = ['url' => $url, 'count' => $h1Count];
            }

            $previousLevel = 0;
            foreach ($structure as $level) {
                if ($previousLevel > 0 && $level > $previousLevel + 1) {
                    $skippedLevel[] = [
                        'url' => $url,
                        'detail' => "H{$previousLevel}'den H{$level}'e atlandı (H" . ($previousLevel + 1) . ' atlanmış)',
                    ];
                    break; // sayfa başına bir örnek yeterli, liste şişmesin
                }
                $previousLevel = $level;
            }
        }

        return [
            'missing_h1' => $missingH1,
            'multiple_h1' => $multipleH1,
            'skipped_level' => $skippedLevel,
        ];
    }
}

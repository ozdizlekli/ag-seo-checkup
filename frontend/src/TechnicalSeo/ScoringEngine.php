<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * ============================================================
 *  TEKNİK SEO PUANLAMA MOTORU — 5 katmanlı mimari
 * ============================================================
 *
 * Kızın eski aracı (14 sabit eşik kuralı, hepsi eşit ağırlıklı) ve senin
 * eski Laravel aracın (sayfa skorlarının düz ortalaması) ile kıyaslayınca
 * geride kaldığımız yerler şunlardı: (1) ikili/eşik-tabanlı puanlama
 * (89 puan = "kötü", 90 puan = "iyi" gibi keskin uçurumlar), (2) her kontrolün
 * eşit ağırlıklı sayılması (SSL sertifikası 3 gün sonra bitecek olması ile
 * bir resimde alt metni eksik olması aynı ağırlıkta sayılıyordu), (3) tek
 * sayfa bazlı değerlendirme (bütün site için "kaç sayfanın etkilendiği"
 * hesaba katılmıyordu), (4) güven seviyesi ayrımı yok (kesin teknik bir
 * gerçek ile yorumlanabilir bir sezgisel sinyal aynı kesinlikte sunuluyordu).
 *
 * Bu motor, Lighthouse (log-normal eğri + kategori ağırlıkları), Ahrefs
 * (hata/uyarı ayrımı), Semrush (önem derecesine göre ağırlıklandırma) ve
 * Sitebulb'ın (severity × prevalence × confidence = "Prioritized Hints")
 * yayınlanmış metodolojilerinden esinlenerek 5 katman uygular:
 *
 *   1) Sürekli puanlama eğrileri (binary "geçti/kaldı" değil)
 *   2) Önem derecesine göre ağırlıklandırılmış kontroller
 *   3) Kategori ağırlıklı bileşik skor
 *   4) Yaygınlık-farkında çok sayfalı toplulaştırma (kaç sayfayı etkiliyor)
 *   5) Güven/kesinlik etiketleme (kesin teknik gerçek vs. yorumlanabilir sinyal)
 *
 * + "Kritik kapı" (hard gate) mekanizması: bazı hatalar o kadar temel ki
 * (örn. site hiç indekslenmiyor), ağırlıklı ortalama ne olursa olsun nihai
 * skoru bir tavana çarptırırlar.
 *
 * ÖNEMLİ KURAL: scoreXxx() fonksiyonlarından biri bir puan kesintisi
 * uyguluyorsa, buildFindings() içinde o kesintiye karşılık gelen bir bulgu
 * da üretilmeli - aksi halde kullanıcı "neden 100 değil" sorusuna arayüzde
 * cevap bulamaz (sessiz puan kaybı).
 */
final class ScoringEngine
{
    /** Kategori ağırlıkları - toplamı 1.0 olmalı. */
    private const CATEGORY_WEIGHTS = [
        'crawlability_indexability' => 0.30,
        'performance' => 0.25,
        'site_structure_links' => 0.15,
        'security_https' => 0.10,
        'schema_structured_data' => 0.10,
        'mobile_first' => 0.10,
    ];

    private const CATEGORY_LABELS = [
        'crawlability_indexability' => 'Taranabilirlik & İndekslenebilirlik',
        'performance' => 'Performans (PageSpeed)',
        'site_structure_links' => 'Site Yapısı & Bağlantılar',
        'security_https' => 'Güvenlik (HTTPS/SSL)',
        'schema_structured_data' => 'Yapılandırılmış Veri (Schema)',
        'mobile_first' => 'Mobil-Öncelikli Uyum',
    ];

    private const SEVERITY_WEIGHTS = ['critical' => 100, 'major' => 60, 'minor' => 25];
    private const CONFIDENCE_WEIGHTS = ['kesin' => 1.0, 'olası' => 0.6];

    /**
     * Ana giriş noktası. api/technical_seo_audit.php tarafından toplanan
     * tüm ham kontrol sonuçlarını alır, nihai skoru ve önceliklendirilmiş
     * bulgu listesini üretir.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function compute(array $input): array
    {
        $totalPages = max(1, $input['crawled_page_count'] ?? 1);

        $categoryScores = [
            'crawlability_indexability' => $this->scoreCrawlability($input['indexability'] ?? [], $totalPages),
            'performance' => $this->scorePerformance($input['psi'] ?? []),
            'site_structure_links' => $this->scoreSiteStructure($input['link_check'] ?? [], $input['site_structure'] ?? []),
            'security_https' => $this->scoreSecurity($input['ssl'] ?? []),
            'schema_structured_data' => $this->scoreSchema($input['schema_issues'] ?? []),
            'mobile_first' => $this->scoreMobileFirst($input['mobile_parity'] ?? []),
        ];

        $weightedAverage = 0.0;
        foreach (self::CATEGORY_WEIGHTS as $key => $weight) {
            $weightedAverage += ($categoryScores[$key]['score'] ?? 0) * $weight;
        }
        $weightedAverage = round($weightedAverage);

        $gates = $this->evaluateHardGates($input);
        $gateCaps = array_column($gates, 'cap');
        $finalScore = empty($gateCaps) ? $weightedAverage : min($weightedAverage, min($gateCaps));

        $findings = $this->buildFindings($input, $totalPages);
        // Önce önem derecesi (critical > major > minor), sonra AYNI önem
        // derecesi içinde öncelik puanına göre sırala. Salt priority_score'a
        // göre sıralamak, çok sayfayı etkileyen düşük önemli bir bulgunun
        // (ör. 100 sayfada "minor" bir canonical notu) az sayfayı etkileyen
        // "major" bir bulgunun ÜSTÜNE çıkmasına yol açıyordu - bu, önem
        // derecesi etiketiyle çelişen, kafa karıştırıcı bir sıralamaydı.
        usort($findings, static function (array $a, array $b): int {
            $severityCompare = (self::SEVERITY_WEIGHTS[$b['severity']] ?? 0) <=> (self::SEVERITY_WEIGHTS[$a['severity']] ?? 0);
            return $severityCompare !== 0 ? $severityCompare : $b['priority_score'] <=> $a['priority_score'];
        });

        return [
            'final_score' => (int) max(0, min(100, $finalScore)),
            'weighted_average_before_gates' => (int) $weightedAverage,
            'category_scores' => array_map(
                static fn (string $key, array $data): array => [
                    'key' => $key,
                    'label' => self::CATEGORY_LABELS[$key],
                    'weight_percent' => (int) round(self::CATEGORY_WEIGHTS[$key] * 100),
                    'score' => $data['score'],
                    'confidence' => $data['confidence'],
                ],
                array_keys($categoryScores),
                $categoryScores
            ),
            'gates_triggered' => $gates,
            'findings' => $findings,
        ];
    }

    /**
     * Kritik kapılar: bazı durumlar site pratikte "görünmez" ya da
     * "güvensiz" hâle geldiği için, ağırlıklı ortalama iyi çıksa bile nihai
     * skoru bir tavana çarptırırlar. Örn: her şey mükemmel olsa bile site
     * noindex ise, o "mükemmellik" hiç kimseye görünmeyecektir.
     *
     * @return list<array{code:string, cap:int, message:string}>
     */
    private function evaluateHardGates(array $input): array
    {
        $gates = [];
        $indexability = $input['indexability'] ?? [];

        if (($indexability['noindex']['noindex'] ?? false) === true) {
            $gates[] = [
                'code' => 'homepage_noindex',
                'cap' => 15,
                'message' => 'Ana sayfa "noindex" ile işaretlenmiş — site, mükemmel teknik durumda olsa bile Google tarafından indekslenemiyor.',
            ];
        }

        if (($indexability['robots_blocks_site'] ?? false) === true) {
            $gates[] = [
                'code' => 'robots_txt_blocks_all',
                'cap' => 15,
                'message' => 'robots.txt tüm siteyi arama motorlarına kapatıyor (Disallow: /) — tarayıcılar siteye giremiyor.',
            ];
        }

        $homepageStatus = $input['homepage_status_code'] ?? 200;
        if ($homepageStatus >= 500) {
            $gates[] = [
                'code' => 'homepage_5xx',
                'cap' => 10,
                'message' => "Ana sayfa sunucu hatası döndürüyor (HTTP {$homepageStatus}) — site şu anda erişilemez durumda.",
            ];
        }

        $ssl = $input['ssl'] ?? [];
        if (($ssl['https_reachable'] ?? false) !== true) {
            $gates[] = [
                'code' => 'no_https',
                'cap' => 50,
                'message' => 'Site HTTPS üzerinden erişilebilir değil — tarayıcılar "Güvenli Değil" uyarısı gösterir ve bu doğrudan bir Google sıralama faktörüdür.',
            ];
        }

        return $gates;
    }

    // ---------------------------------------------------------------
    // Kategori skorlama fonksiyonları (her biri 0-100 arası SÜREKLİ bir
    // değer döner - ikili/eşik tabanlı değil).
    // ---------------------------------------------------------------

    private function scoreCrawlability(array $indexability, int $totalPages): array
    {
        $score = 100.0;

        if (($indexability['noindex']['noindex'] ?? false) === true) {
            $score -= 100;
        }
        if (($indexability['robots_blocks_site'] ?? false) === true) {
            $score -= 100;
        }
        if (($indexability['robots_txt_found'] ?? true) === false) {
            $score -= 5;
        }
        if (($indexability['sitemap_found'] ?? true) === false) {
            $score -= 15;
        } elseif (($indexability['sitemap_url_count'] ?? 0) === 0) {
            $score -= 10;
        }

        $canonical = $indexability['canonical'] ?? [];
        if (($canonical['present'] ?? false) === false) {
            $score -= 8;
        } elseif (($canonical['is_self_referencing'] ?? true) === false) {
            $score -= 5;
        }

        $orphanRatio = (float) ($indexability['orphan_page_ratio_percent'] ?? 0);
        $score -= min(30, $orphanRatio);

        // YENİ: yinelenen title/meta description - "kaç sayfayı etkiliyor"
        // oranına göre kesinti (orphan_page_ratio_percent ile aynı mantık).
        // Title yinelenmesi daha ağır (arama motorunun sayfaları ayırt etmesini
        // zorlaştırır); meta description yinelenmesi daha hafif (öncelikle CTR'ı etkiler).
        $duplicateTitleAffected = $this->countDuplicateAffectedPages($indexability['duplicate_titles'] ?? [], true);
        $duplicateMetaAffected = $this->countDuplicateAffectedPages($indexability['duplicate_meta_descriptions'] ?? [], true);
        $score -= min(15, ($duplicateTitleAffected / $totalPages) * 100 * 0.5);
        $score -= min(10, ($duplicateMetaAffected / $totalPages) * 100 * 0.3);

        // YENİ: H1-H6 başlık hiyerarşisi - eksik H1 en önemlisi (sayfanın ana
        // konusu sinyali kayıp), birden fazla H1 daha hafif bir kesinti.
        $headingHierarchy = $indexability['heading_hierarchy'] ?? [];
        $missingH1Count = count($headingHierarchy['missing_h1'] ?? []);
        $multipleH1Count = count($headingHierarchy['multiple_h1'] ?? []);
        $score -= min(15, ($missingH1Count / $totalPages) * 100 * 0.5);
        $score -= min(8, ($multipleH1Count / $totalPages) * 100 * 0.3);

        return ['score' => (int) max(0, min(100, round($score))), 'confidence' => 'kesin'];
    }

    /**
     * @param list<array{value:string, urls:list<string>}> $duplicateGroups
     */
    private function countDuplicateAffectedPages(array $duplicateGroups, bool $onlyUnresolved = false): int
    {
        $count = 0;
        foreach ($duplicateGroups as $group) {
            if ($onlyUnresolved && ($group['status'] ?? 'unresolved') === 'resolved') {
                continue; // canonical ile temiz sekilde cozulmus gruplar puani dusurmemeli
            }
            $count += count($group['urls'] ?? []);
        }
        return $count;
    }

    private function scorePerformance(array $psi): array
    {
        $mobile = $psi['mobile']['category_scores'] ?? null;
        $desktop = $psi['desktop']['category_scores'] ?? null;

        if ($mobile === null && $desktop === null) {
            return ['score' => 0, 'confidence' => 'kesin']; // PSI hiç veri döndüremediyse (API hatası) 0 - ayrıca hata ayrıca raporlanır.
        }

        // Mobil-öncelikli indeksleme gerçeği: mobil skor %70, masaüstü %30 ağırlıklı.
        $mobilePerf = $mobile['performance'] ?? null;
        $desktopPerf = $desktop['performance'] ?? null;

        if ($mobilePerf !== null && $desktopPerf !== null) {
            $blended = ($mobilePerf * 0.7) + ($desktopPerf * 0.3);
        } else {
            $blended = $mobilePerf ?? $desktopPerf ?? 0;
        }

        return ['score' => (int) round($blended), 'confidence' => 'kesin'];
    }

    /**
     * scorePerformance()'daki mobil-oncelikli (0.7/0.3) harmanlama mantiginin
     * ayni sekilde - PSI'nin diger kategori skorlari (accessibility,
     * best-practices) icin bulgu uretirken tek bir sayi elde etmek amaciyla
     * kullanilir. scorePerformance()'in kendi hesaplamasina BILEREK
     * dokunmuyoruz (zaten calisiyor, test edilmis) - bu sadece asagidaki
     * YENI bulgu bloklarinin kullandigi ayri bir yardimci fonksiyon.
     */
    private function blendMobileDesktopScore(?int $mobile, ?int $desktop): ?int
    {
        if ($mobile === null && $desktop === null) {
            return null;
        }
        if ($mobile !== null && $desktop !== null) {
            return (int) round(($mobile * 0.7) + ($desktop * 0.3));
        }
        return $mobile ?? $desktop;
    }

    private function scoreSiteStructure(array $linkCheck, array $siteStructure): array
    {
        $score = 100.0;

        $checked = $linkCheck['checked_count'] ?? 0;
        $brokenCount = count($linkCheck['broken'] ?? []);
        if ($checked > 0) {
            $brokenRatio = $brokenCount / $checked;
            $score -= min(50, $brokenRatio * 100 * 1.2);
        }

        $pages = $siteStructure['pages'] ?? [];
        if (!empty($pages)) {
            $deadEndPages = count(array_filter($pages, static fn (array $p): bool => ($p['internal_link_count'] ?? 0) === 0));
            $deadEndRatio = $deadEndPages / count($pages);
            $score -= min(20, $deadEndRatio * 100 * 0.5);
        }

        return ['score' => (int) max(0, min(100, round($score))), 'confidence' => 'kesin'];
    }

    private function scoreSecurity(array $ssl): array
    {
        if (($ssl['https_reachable'] ?? false) !== true) {
            return ['score' => 0, 'confidence' => 'kesin'];
        }

        if (($ssl['valid'] ?? false) !== true) {
            return ['score' => 20, 'confidence' => 'kesin'];
        }

        $daysRemaining = $ssl['days_remaining'] ?? null;
        if ($daysRemaining === null) {
            return ['score' => 70, 'confidence' => 'olası'];
        }
        if ($daysRemaining < 14) {
            return ['score' => 60, 'confidence' => 'kesin'];
        }
        if ($daysRemaining < 30) {
            return ['score' => 80, 'confidence' => 'kesin'];
        }

        return ['score' => 100, 'confidence' => 'kesin'];
    }

    private function scoreSchema(array $issues): array
    {
        $score = 100.0;
        foreach ($issues as $issue) {
            $severity = $issue['severity'] ?? 'minor';
            $score -= match ($severity) {
                'critical' => 40,
                'major' => 20,
                default => 8,
            };
        }

        return ['score' => (int) max(0, min(100, round($score))), 'confidence' => 'kesin'];
    }

    private function scoreMobileFirst(array $parity): array
    {
        if (($parity['comparable'] ?? false) !== true) {
            return ['score' => 70, 'confidence' => 'olası'];
        }

        $score = 100.0;
        $dropPercent = (float) ($parity['word_count_drop_percent'] ?? 0);

        if ($dropPercent > 0) {
            $score -= min(60, $dropPercent * 1.5);
        }
        if (($parity['structured_data_lost_on_mobile'] ?? false) === true) {
            $score -= 20;
        }

        return ['score' => (int) max(0, min(100, round($score))), 'confidence' => 'olası'];
    }

    // ---------------------------------------------------------------
    // Önceliklendirilmiş bulgu listesi
    // priority_score = severity_weight × (affected_pages/total_pages) × confidence_weight
    // (Sitebulb'ın "Prioritized Hints" yaklaşımından esinlenilmiştir.)
    // ---------------------------------------------------------------

    /**
     * @return list<array{
     *   category:string, severity:string, confidence:string, title:string,
     *   detail:string, how_to_fix:string, affected_pages:int, priority_score:float,
     *   items:list<array{url:string, status:string}>
     * }>
     */
    private function buildFindings(array $input, int $totalPages): array
    {
        $findings = [];
        $add = function (string $category, string $severity, string $confidence, string $title, string $detail, string $howToFix, int $affectedPages, array $items = [], string $itemNoun = 'sayfa') use (&$findings, $totalPages): void {
            $severityWeight = self::SEVERITY_WEIGHTS[$severity] ?? 25;
            $confidenceWeight = self::CONFIDENCE_WEIGHTS[$confidence] ?? 0.6;
            $prevalence = min(1.0, $affectedPages / $totalPages);

            $solutionType = 'manual_steps';
            if (stripos($title, 'sitemap') !== false) $solutionType = 'generated_sitemap';
            elseif (stripos($title, 'canonical') !== false) $solutionType = 'code_snippet';
            elseif (stripos($title, 'robots') !== false) $solutionType = 'generated_robots';
            elseif (stripos($title, 'şema') !== false || stripos($title, 'schema') !== false) $solutionType = 'json_ld';
            elseif (stripos($title, 'PageSpeed') !== false || $category === 'performance') $solutionType = 'configuration_example';

            $findings[] = [
                'id' => substr(hash('sha256', $category . '|' . $title), 0, 16),
                'category' => $category,
                'severity' => $severity,
                'confidence' => $confidence,
                'title' => $title,
                'detail' => $detail,
                'how_to_fix' => $howToFix,
                'affected_pages' => $affectedPages,
                'priority_score' => round($severityWeight * max($prevalence, $affectedPages > 0 ? 0.05 : 0) * $confidenceWeight, 2),
                // Bazı bulgular (ör. kırık linkler) tek tek örnek/URL listesi
                // taşıyabilir - arayüz bunu varsa tıklanınca açılan bir liste
                // olarak gösterir. Yoksa boş dizi (arayüz tarafında tutarlı şema).
                'items' => $items,
                // 'items' listesindeki her satırın NE olduğunu (sayfa mı, link
                // mi) arayüze bildiriyor - eskiden arayüz bunu hep "link" diye
                // sabitlemişti, bu da ör. "319 link - göster" gibi (aslında
                // sayfa olan) bulgularda yanlış/yanıltıcı görünüyordu.
                'item_noun' => $itemNoun,
                'why_it_matters' => $detail,
                'source' => $category === 'performance' ? ['pagespeed'] : ['crawler'],
                'solution_type' => $solutionType,
                'solution_available' => $howToFix !== '',
                'manual_review_required' => $confidence !== 'kesin',
                'verification_steps' => [
                    'Önerilen değişikliği önce test veya staging ortamında uygulayın.',
                    'İlgili URL’yi yeniden denetleyin ve bulgunun kapanıp kapanmadığını doğrulayın.',
                ],
            ];
        };

        $indexability = $input['indexability'] ?? [];

        if (($indexability['noindex']['noindex'] ?? false) === true) {
            $add('crawlability_indexability', 'critical', 'kesin', 'Sayfa noindex ile işaretli',
                'Meta robots etiketi veya X-Robots-Tag header\'ı "noindex" içeriyor.',
                'Sayfanın indekslenmesini istiyorsanız <meta name="robots" content="noindex"> etiketini ve varsa X-Robots-Tag: noindex header\'ını kaldırın.',
                $totalPages);
        }

        if (($indexability['robots_blocks_site'] ?? false) === true) {
            $add('crawlability_indexability', 'critical', 'kesin', 'robots.txt tüm siteyi engelliyor',
                'robots.txt dosyasında "Disallow: /" kuralı tüm arama motoru botlarını engelliyor.',
                'robots.txt dosyasındaki "Disallow: /" satırını kaldırın veya sadece gerçekten gizlenmesi gereken yolları belirtin.',
                $totalPages);
        }

        if (($indexability['sitemap_found'] ?? true) === false) {
            $add('crawlability_indexability', 'major', 'kesin', 'sitemap.xml bulunamadı',
                '/sitemap.xml adresinde geçerli bir sitemap dosyası bulunamadı.',
                'Bir XML sitemap oluşturup /sitemap.xml adresinde yayınlayın ve robots.txt içine "Sitemap:" satırı ile ekleyin.',
                $totalPages);
        }

        // scoreCrawlability() canonical eksikliğine/kendine-referans-vermemesine
        // -8/-5 puan kesiyor - buradaki bulgu olmadan kullanıcı bu kesintinin
        // sebebini arayüzde göremezdi (bkz. sınıf başındaki not).
        $canonical = $indexability['canonical'] ?? [];
        if (($canonical['present'] ?? false) === false) {
            $add('crawlability_indexability', 'minor', 'kesin', 'Canonical etiketi yok',
                'Sayfada <link rel="canonical"> etiketi bulunamadı.',
                'Her sayfaya kendi URL\'sine (ya da yinelenen içerik varsa tercih edilen sürüme) işaret eden bir canonical etiketi ekleyin.',
                $totalPages);
        } elseif (($canonical['is_self_referencing'] ?? true) === false) {
            $canonicalUrl = $canonical['canonical_url'] ?? null;
            $add('crawlability_indexability', 'minor', 'kesin', 'Canonical başka bir URL\'e işaret ediyor',
                'Sayfanın canonical etiketi kendi URL\'si yerine '
                    . ($canonicalUrl !== null ? "şu adrese işaret ediyor: {$canonicalUrl}" : 'başka bir adrese işaret ediyor')
                    . ' - bu kasıtlı olabilir (yinelenen içerik yönetimi) ama yanlışlıkla da olmuş olabilir.',
                'Bu yönlendirmenin kasıtlı olduğundan emin olun; değilse canonical etiketini sayfanın kendi URL\'sini gösterecek şekilde düzeltin.',
                $totalPages);
        }

        $jsDependency = $indexability['js_dependency'] ?? [];
        if (($jsDependency['likely_js_dependent'] ?? false) === true) {
            $add('crawlability_indexability', 'major', 'olası', 'Kritik içerik JavaScript\'e bağımlı olabilir',
                'Ana sayfanın ham HTML\'i (JavaScript çalışmadan önceki hali) neredeyse boş görünüyor (görünür metin yaklaşık '
                    . ($jsDependency['visible_text_length'] ?? 0) . ' karakter). Google Arama JavaScript\'i çalıştırır ama '
                    . 'GPTBot, ClaudeBot, PerplexityBot gibi birçok AI/arama botu sayfayı olduğu gibi, JavaScript çalıştırmadan okur - '
                    . 'bu botlar sayfanın gerçek içeriğini hiç görmüyor olabilir.',
                'Kritik içeriğin (başlıklar, ana metin, ürün/hizmet bilgisi) sunucu tarafında render edilmiş (SSR) ham HTML\'de de '
                    . 'bulunmasını sağlayın, ya da en azından arama/AI botları için bir prerender/statik HTML alternatifi sunun. '
                    . 'Bu bir heuristik/ipucudur - gerçek bir tarayıcı ile render edilmiş sonuçla teyit etmenizde fayda var.',
                $totalPages);
        }

        // YENİ: yinelenen title/meta description bulguları artık canonical-farkında.
        // Her grup OnPageContentChecker tarafından 'resolved' (canonical ile büyük
        // çoğunluk aynı, geçerli, doğrudan bir hedefe işaret ediyor) veya 'unresolved'
        // olarak etiketlenmiş geliyor. Sadece UNRESOLVED gruplar "gerçek" bir yinelenen
        // içerik sorunu olarak raporlanır; resolved gruplar için ayrıca aşağıda 3 farklı
        // canonical bulgusu (yapısal hata / bozuk-zincir-döngü / inceleme gerekli)
        // üretiliyor - bkz. OnPageContentChecker sınıf docblock'u.
        $duplicateTitles = $indexability['duplicate_titles'] ?? [];
        $duplicateMetaDescriptions = $indexability['duplicate_meta_descriptions'] ?? [];

        $canonicalReasonLabels = [
            'conflicting_target' => 'farklı bir canonical hedefi belirtiyor (grup çoğunluğuyla uyuşmuyor)',
            'cross_domain' => 'canonical başka bir alan adına (domain) işaret ediyor',
            'unverified' => 'canonical hedefi taranan sayfalar arasında bulunamadı (doğrulanamadı)',
            'no_majority' => 'gruptaki sayfaların çoğunluğu aynı canonical hedefinde anlaşamıyor',
            'tied_targets' => 'gruptaki sayfalar farklı canonical hedeflerine işaret ediyor, net bir çoğunluk yok (sayfalar birbiriyle çelişiyor)',
            'self_or_missing' => 'bu sayfada canonical etiketi yok',
            'broken_target' => 'canonical hedefi geçersiz bir durum kodu (4xx/5xx) döndürüyor',
            'target_redirects' => "canonical hedefi kendisi başka bir URL'e yönlendiriyor",
            'target_noindex' => 'canonical hedefi noindex ile işaretli (arama motoru zaten indekslemeyecek)',
            'chain' => "canonical zinciri var (hedefin canonical'i başka bir sayfayı gösteriyor)",
            'loop' => 'canonical döngüsü tespit edildi (A → B → A)',
            'structural_issue' => 'bu sayfanın canonical etiketinde yapısal bir hata var',
        ];
        $brokenCanonicalReasons = ['broken_target', 'target_redirects', 'target_noindex', 'chain', 'loop'];
        // NOT: 'self_or_missing' bilerek bu listede DEĞİL - bir sayfada canonical
        // etiketi hiç yoksa bu belirsiz/inceleme-gerektiren bir sinyal değildir,
        // sadece sinyalin YOKLUĞUDUR; bu durum zaten yukarıdaki (1) numaralı
        // klasik yinelenen title/meta bulgusunun kendi metninde (canonical eklemek
        // bir çözüm seçeneği olarak) açıklanıyor - ayrı bir "inceleme gerekli"
        // bulgusuyla tekrar etmek gürültü yaratır.
        $reviewCanonicalReasons = ['cross_domain', 'unverified', 'no_majority', 'conflicting_target', 'tied_targets'];

        $collectCanonicalItems = static function (array $groups, array $wantedReasons) use ($canonicalReasonLabels): array {
            $items = [];
            $seen = [];
            foreach ($groups as $group) {
                if (($group['status'] ?? 'unresolved') === 'resolved') {
                    continue;
                }
                foreach (($group['unresolved_details'] ?? []) as $detail) {
                    $reason = $detail['reason'] ?? '';
                    if (!in_array($reason, $wantedReasons, true)) {
                        continue;
                    }
                    $key = $detail['url'] . '|' . $reason;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $items[] = ['url' => $detail['url'], 'status' => $canonicalReasonLabels[$reason] ?? $reason];
                }
            }
            return $items;
        };

        // 1) Klasik yinelenen title bulgusu - SADECE canonical ile çözülmemiş gruplar sayılır.
        $unresolvedTitleGroups = array_values(array_filter(
            $duplicateTitles,
            static fn (array $g): bool => ($g['status'] ?? 'unresolved') !== 'resolved'
        ));
        if (!empty($unresolvedTitleGroups)) {
            $duplicateTitleItems = [];
            foreach ($unresolvedTitleGroups as $group) {
                foreach (($group['urls'] ?? []) as $url) {
                    $duplicateTitleItems[] = ['url' => $url, 'status' => 'paylaşılan title: "' . $group['value'] . '"'];
                }
            }
            $add('crawlability_indexability', 'major', 'kesin',
                count($unresolvedTitleGroups) . ' grup halinde yinelenen title (toplam ' . count($duplicateTitleItems) . ' sayfayı etkiliyor)',
                'Birden fazla sayfa birebir aynı <title> içeriğine sahip ve bu durum geçerli bir canonical etiketiyle '
                    . 'açıklanmıyor - bu, arama motorlarının sayfaları birbirinden ayırt etmesini zorlaştırır ve arama '
                    . 'sonuçlarında hangi sayfanın gösterileceğine dair belirsizlik yaratabilir.',
                'Bu sayfalar gerçekten farklı içerikler sunuyorsa her birine kendi title\'ını yazın. Eğer bunlar aynı '
                    . 'içeriğin filtrelenmiş/parametreli varyasyonlarıysa (ör. ?sektor=... gibi facet\'ler), her birine '
                    . 'ayrı title yazmak yerine birincil sayfaya <link rel="canonical"> ile işaret edin.',
                count($duplicateTitleItems), $duplicateTitleItems);
        }

        $unresolvedMetaGroups = array_values(array_filter(
            $duplicateMetaDescriptions,
            static fn (array $g): bool => ($g['status'] ?? 'unresolved') !== 'resolved'
        ));
        if (!empty($unresolvedMetaGroups)) {
            $duplicateMetaItems = [];
            foreach ($unresolvedMetaGroups as $group) {
                foreach (($group['urls'] ?? []) as $url) {
                    $duplicateMetaItems[] = ['url' => $url, 'status' => 'paylaşılan açıklama'];
                }
            }
            $add('crawlability_indexability', 'minor', 'kesin',
                count($unresolvedMetaGroups) . ' grup halinde yinelenen meta description (toplam ' . count($duplicateMetaItems) . ' sayfayı etkiliyor)',
                'Birden fazla sayfa birebir aynı meta description içeriğine sahip ve bu durum geçerli bir canonical '
                    . 'etiketiyle açıklanmıyor - bu sıralamayı doğrudan etkilemez ama arama sonuçlarındaki tıklama '
                    . 'oranını (CTR) düşürebilir.',
                'Bu sayfalar gerçekten farklı içerikler sunuyorsa her birine özel bir açıklama yazın. Eğer bunlar aynı '
                    . 'içeriğin varyasyonlarıysa, birincil sayfaya canonical ile işaret etmek de bu tekrarı meşrulaştırır.',
                count($duplicateMetaItems), $duplicateMetaItems);
        }

        // 2) Sayfanın KENDİ canonical etiketindeki yapısal hatalar (çoklu/boş href/geçersiz protokol) -
        // duplicate grubuna dahil olsun olmasın, HER ZAMAN ayrı bir bulgu.
        $canonicalStructuralIssues = $indexability['canonical_structural_issues'] ?? [];
        if (!empty($canonicalStructuralIssues)) {
            $structuralIssueLabels = [
                'multiple' => 'sayfada birden fazla canonical etiketi var',
                'empty_href' => 'canonical etiketinin href değeri boş',
                'invalid_scheme' => 'canonical hedefi http(s) dışında bir protokole çözümleniyor',
            ];
            $structuralItems = array_map(
                static fn (array $i): array => ['url' => $i['url'], 'status' => $structuralIssueLabels[$i['issue']] ?? $i['issue']],
                $canonicalStructuralIssues
            );
            $add('crawlability_indexability', 'major', 'kesin',
                'Canonical etiketinde yapısal hata (' . count($canonicalStructuralIssues) . ' sayfa)',
                'Bu sayfalardaki <link rel="canonical"> etiketi kendi içinde hatalı - birden fazla canonical etiketi, '
                    . 'boş bir href, veya http(s) dışında bir protokol içeriyor. Bu durumda arama motorları canonical '
                    . 'sinyalini güvenilir şekilde kullanamaz.',
                'Her sayfada TEK bir <link rel="canonical" href="..."> etiketi bulunmalı; href değeri geçerli, dolu ve '
                    . 'http:// veya https:// ile başlayan mutlak/göreli bir URL olmalı.',
                count($canonicalStructuralIssues), $structuralItems);
        }

        // 3) Canonical HEDEFİ bozuk/yönlendiriyor/zincirleniyor/döngüde - "sitenin kendi beyanı"
        // güvenilir değil. Hem title hem meta duplicate gruplarından toplanır, aynı url+sebep
        // ikilisi tekrar sayılmaz.
        $brokenCanonicalItems = $collectCanonicalItems(
            array_merge($duplicateTitles, $duplicateMetaDescriptions),
            $brokenCanonicalReasons
        );
        if (!empty($brokenCanonicalItems)) {
            $add('crawlability_indexability', 'major', 'kesin',
                'Canonical hedefi bozuk, yönlendiriyor veya döngüde (' . count($brokenCanonicalItems) . ' sayfa)',
                'Bu sayfalar bir canonical hedefi belirtiyor ama o hedef ya çalışmıyor (4xx/5xx), ya kendisi başka bir '
                    . "URL'e yönlendiriyor, ya NOINDEX ile işaretli (arama motoru zaten indekslemeyecek), ya bir canonical "
                    . "ZİNCİRİ oluşturuyor (hedefin canonical'i yine başka bir sayfayı gösteriyor) ya da bir DÖNGÜ içinde "
                    . "(A sayfası B'yi, B de A'yı gösteriyor). Bu durumların hepsinde arama motoru hangi sayfanın asil "
                    . 'sayfa olduğuna kendi başına karar vermek zorunda kalır.',
                'Canonical etiketlerinin doğrudan, çalışan (2xx) ve TEK bir gerçek/birincil sayfaya işaret ettiğinden '
                    . 'emin olun - zincirleme canonical\'lardan kaçının, her sayfa doğrudan nihai hedefe işaret etsin.',
                count($brokenCanonicalItems), $brokenCanonicalItems);
        }

        // 4) İnceleme gerektiren durumlar (çapraz domain, doğrulanamayan hedef, çelişkili/eksik
        // canonical) - bunlar KESİN bir hata değil, bu yüzden severity daha düşük ve confidence 'olası'.
        // NOT: canonical hedefinin İÇERİK düzeyinde gerçekten doğru sayfa olup olmadığı bu otomatik
        // denetimin KAPSAMI DIŞINDADIR - burada sadece hedefin ulaşılabilir/tutarlı olup olmadığına bakılır.
        $reviewCanonicalItems = $collectCanonicalItems(
            array_merge($duplicateTitles, $duplicateMetaDescriptions),
            $reviewCanonicalReasons
        );
        if (!empty($reviewCanonicalItems)) {
            $add('crawlability_indexability', 'minor', 'olası',
                'Canonical incelemesi gerekli (' . count($reviewCanonicalItems) . ' sayfa)',
                'Bu sayfalar başka bir sayfayla aynı title/description\'ı paylaşıyor ve durum canonical etiketiyle '
                    . 'KESİN olarak açıklığa kavuşturulamadı - hedef başka bir alan adında, taranan sayfalar arasında '
                    . 'bulunamadı (crawl sayfa limitine takılmış olabilir), gruptaki sayfalar farklı hedeflere işaret '
                    . "ediyor, ya da sayfada hiç canonical etiketi yok. Bu KESİN bir hata olmayabilir - manuel kontrol "
                    . 'önerilir. (Not: canonical hedefinin İÇERİK olarak da doğru sayfa olduğu bu otomatik denetimle '
                    . 'doğrulanamaz - sadece teknik ulaşılabilirlik/tutarlılık kontrol edilir.)',
                'Her grubu elle kontrol edin: sayfalar gerçekten aynı içeriği mi gösteriyor? Öyleyse doğru, ulaşılabilir '
                    . 've aynı alan adındaki bir birincil sayfaya canonical ekleyin/düzeltin.',
                count($reviewCanonicalItems), $reviewCanonicalItems);
        }

        $headingHierarchy = $indexability['heading_hierarchy'] ?? [];
        $missingH1 = $headingHierarchy['missing_h1'] ?? [];
        if (!empty($missingH1)) {
            $missingH1Items = array_map(
                static fn (string $url): array => ['url' => $url, 'status' => 'H1 yok'],
                $missingH1
            );
            $add('crawlability_indexability', 'major', 'kesin', count($missingH1) . ' sayfada H1 başlığı yok',
                'Bu sayfalarda hiç <h1> etiketi bulunamadı - H1, sayfanın ana konusunu hem kullanıcıya hem arama motorlarına bildiren temel semantik işarettir.',
                'Her sayfaya, sayfanın ana konusunu özetleyen tek bir <h1> etiketi ekleyin.',
                count($missingH1Items), $missingH1Items);
        }

        $multipleH1 = $headingHierarchy['multiple_h1'] ?? [];
        if (!empty($multipleH1)) {
            $multipleH1Items = array_map(
                static fn (array $m): array => ['url' => $m['url'], 'status' => $m['count'] . ' adet H1'],
                $multipleH1
            );
            $add('crawlability_indexability', 'minor', 'kesin', count($multipleH1) . ' sayfada birden fazla H1 var',
                'Bu sayfalarda tek bir ana başlık yerine birden fazla <h1> etiketi kullanılmış - modern arama motorları '
                    . 'bunu tolere etse de, sayfanın "ana konusu" sinyalini zayıflatabilir.',
                'Sayfa başına tek bir <h1> kullanın; diğer başlıkları <h2>/<h3> gibi alt seviyelere indirin.',
                count($multipleH1Items), $multipleH1Items);
        }

        $skippedLevel = $headingHierarchy['skipped_level'] ?? [];
        if (!empty($skippedLevel)) {
            $skippedLevelItems = array_map(
                static fn (array $s): array => ['url' => $s['url'], 'status' => $s['detail']],
                $skippedLevel
            );
            $add('crawlability_indexability', 'minor', 'olası', count($skippedLevel) . ' sayfada başlık seviyesi atlanmış',
                'Bu sayfalarda başlık hiyerarşisinde bir seviye atlanmış (ör. H1\'den sonra doğrudan H3) - bu çoğunlukla '
                    . 'bir tasarım/CSS tercihi olabilir ama ekran okuyucular ve arama motorları için doküman yapısını bozabilir.',
                'Başlık seviyelerini sırayla kullanın (H1 → H2 → H3 ...) - bir seviyeyi sadece görsel olarak küçültmek '
                    . 'istiyorsanız bunu CSS ile yapın, HTML seviyesini atlamayın.',
                count($skippedLevelItems), $skippedLevelItems);
        }

        // NOT - iki farklı kavram burada kesişiyor, karıştırılmamalı:
        //  - "sitemap dışı sayfa": tarama sırasında BULUNDU (yani en az bir iç
        //    linkle erişilebilir) ama sitemap.xml'de YOK. Aşağıdaki finding.
        //  - "yetim sayfa (orphan page)": sitemap.xml'de VAR ama tarama
        //    sırasında hiçbir iç linkle ULAŞILAMADI. Bir sonraki finding.
        // Önceden ikisi de yanlışlıkla "orphan page" diye etiketleniyordu.
        $sitemapMissingPages = $indexability['orphan_pages'] ?? [];
        if (!empty($sitemapMissingPages)) {
            // Kırık linklerdeki ile aynı desen: TAM liste 'items' üzerinden
            // gidiyor, kart tıklanınca açılıp hepsini gösteriyor - detay
            // metninde artık sadece ilk birkaçını saymaya gerek yok.
            $sitemapMissingItems = array_map(
                static fn (string $url): array => ['url' => $url, 'status' => "sitemap'te yok"],
                $sitemapMissingPages
            );
            $add('crawlability_indexability', 'major', 'kesin', count($sitemapMissingPages) . ' sayfa sitemap dışı (sitemap.xml\'de eksik)',
                'Site içinde en az bir iç linkle erişilebilen, ancak sitemap.xml\'de yer almayan sayfalar tespit edildi.',
                'Bu sayfaları sitemap.xml\'e ekleyin ki arama motorları tarafından keşfedilme olasılığı artsın.',
                count($sitemapMissingPages), $sitemapMissingItems);
        }

        $orphanPages = $indexability['sitemap_only_pages'] ?? [];
        if (!empty($orphanPages)) {
            $orphanItems = array_map(
                static fn (string $url): array => ['url' => $url, 'status' => 'iç linkle ulaşılamadı'],
                $orphanPages
            );
            $add('crawlability_indexability', 'minor', 'olası', count($orphanPages) . ' yetim sayfa (orphan page) tespit edildi',
                'Bu sayfalar sitemap.xml\'de listelenmiş ama tarama sırasında hiçbir sayfadan bunlara giden bir iç link '
                    . 'bulunamadı - arama motorları sitemap üzerinden bu sayfaları bulsa bile, iç link olmadan sayfanın '
                    . 'önem/otorite sinyali (internal PageRank) çok zayıf kalır.',
                'Bu sayfalara ilgili içeriklerden en az bir iç link ekleyin (ör. menü, ilgili yazılar, footer) ki hem '
                    . 'kullanıcılar hem arama motorları bunlara doğal bir gezinme yoluyla ulaşabilsin.',
                count($orphanItems), $orphanItems);
        }

        $ssl = $input['ssl'] ?? [];
        if (($ssl['https_reachable'] ?? false) !== true) {
            $add('security_https', 'critical', 'kesin', 'HTTPS aktif değil',
                'Site HTTPS üzerinden erişilemiyor.',
                'Sitenize geçerli bir SSL/TLS sertifikası kurun (Let\'s Encrypt ücretsizdir) ve tüm trafiği HTTPS\'e yönlendirin.',
                $totalPages);
        } elseif (($ssl['days_remaining'] ?? 999) < 14) {
            $add('security_https', 'major', 'kesin', 'SSL sertifikası yakında bitiyor',
                'SSL sertifikasının süresi ' . ($ssl['days_remaining'] ?? '?') . ' gün içinde doluyor.',
                'Sertifikanızı şimdiden yenileyin - otomatik yenileme (örn. Let\'s Encrypt cron) kurmayı düşünün.',
                $totalPages);
        }

        foreach (($input['schema_issues'] ?? []) as $issue) {
            $add('schema_structured_data', $issue['severity'] ?? 'minor', 'kesin', 'Şema sorunu: ' . ($issue['type'] ?? ''),
                $issue['message'] ?? '', 'schema.org belgelerine göre eksik/hatalı alanları tamamlayın ve Google\'ın Zengin Sonuç Testi ile doğrulayın.',
                1);
        }

        $linkCheck = $input['link_check'] ?? [];
        $brokenLinks = $linkCheck['broken'] ?? [];
        if (!empty($brokenLinks)) {
            // Bulgunun kendisi hangi linklerin kırık olduğunu TAM listeyle
            // taşır ($items) - arayüzde bulgu kartına tıklanınca bu liste
            // açılıp gösterilir, kullanıcı ayrı bir yere bakmak zorunda kalmaz.
            $brokenItems = array_map(
                static function (array $b): array {
                    return [
                        'url' => $b['url'],
                        'status' => $b['status_code'] > 0 ? "HTTP {$b['status_code']}" : ($b['error'] ?? 'bağlantı hatası'),
                    ];
                },
                $brokenLinks
            );
            $add('site_structure_links', 'major', 'kesin', count($brokenLinks) . ' kırık link tespit edildi',
                'Taranan linkler arasında 4xx/5xx durum kodu dönen veya erişilemeyen linkler bulundu.',
                'Kırık linkleri güncelleyin veya kaldırın; taşınan sayfalar için 301 yönlendirmesi ekleyin.',
                count($brokenLinks), $brokenItems, 'link');
        }

        $mobileParity = $input['mobile_parity'] ?? [];
        if (($mobileParity['significant_content_loss'] ?? false) === true) {
            $add('mobile_first', 'major', 'olası', 'Mobilde önemli ölçüde içerik kaybı',
                'Mobil User-Agent ile çekilen HTML, masaüstü sürümüne göre %' . ($mobileParity['word_count_drop_percent'] ?? '?') . ' daha az kelime içeriyor.',
                'Mobil ve masaüstü sürümlerin aynı içeriği sunduğundan emin olun - Google artık siteyi öncelikle mobil sürümünden değerlendiriyor (mobile-first indexing).',
                $totalPages);
        }

        $psiErrors = array_merge($input['psi']['errors'] ?? []);
        foreach ($psiErrors as $strategy => $errorMessage) {
            $add('performance', 'minor', 'kesin', "PageSpeed API hatası ({$strategy})", $errorMessage,
                'Hata mesajını kontrol edin - genellikle API kotası, geçersiz URL veya .env dosyasında eksik anahtar kaynaklıdır.', 1);
        }

        // YENİ: Erişilebilirlik ve En İyi Uygulamalar skorları PSI yanıtında
        // zaten geliyordu (ekstra API çağrısı gerekmiyor) ama şimdiye kadar
        // sadece arayüzde birer gösterge (gauge) olarak duruyordu, hiçbir
        // bulguya dönüşmüyordu. Eşikler PSI'nin kendi renk skalasıyla aynı:
        // 90+ iyi (bulgu yok), 50-89 turuncu (minor), <50 kırmızı (major).
        $psiMobileScores = $input['psi']['mobile']['category_scores'] ?? [];
        $psiDesktopScores = $input['psi']['desktop']['category_scores'] ?? [];

        $psiScoreFindingLabels = [
            'accessibility' => 'Erişilebilirlik',
            'best-practices' => 'En İyi Uygulamalar',
        ];
        foreach ($psiScoreFindingLabels as $scoreKey => $label) {
            $blended = $this->blendMobileDesktopScore($psiMobileScores[$scoreKey] ?? null, $psiDesktopScores[$scoreKey] ?? null);
            if ($blended === null || $blended >= 90) {
                continue; // veri yok ya da PSI'nin kendi eşiğine göre zaten iyi
            }
            $severity = $blended < 50 ? 'major' : 'minor';
            $mobileDisplay = $psiMobileScores[$scoreKey] ?? '—';
            $desktopDisplay = $psiDesktopScores[$scoreKey] ?? '—';
            $add('performance', $severity, 'kesin', "{$label} skoru düşük ({$blended}/100)",
                "Google PageSpeed Insights, bu sayfa için {$label} kategorisinde {$blended}/100 puan veriyor "
                    . "(mobil: {$mobileDisplay}, masaüstü: {$desktopDisplay}). "
                    . ($scoreKey === 'accessibility'
                        ? 'Bu genelde yetersiz renk kontrastı, eksik alternatif metinler, form etiketleri veya ARIA kullanımı gibi sorunlardan kaynaklanır.'
                        : 'Bu genelde eksik güvenlik başlıkları, tarayıcı konsolu hataları, eski API kullanımı veya görsellerde yanlış en-boy oranı gibi sorunlardan kaynaklanır.'),
                'PageSpeed Insights raporunu (pagespeed.web.dev) açıp "' . $label . '" bölümündeki kırmızı/turuncu maddeleri tek tek inceleyin ve düzeltin.',
                1);
        }

        // YENİ: lab_field_mismatch PageSpeedClient'ta zaten hesaplanıyordu
        // ama hiçbir yerde okunmuyordu (ölü kod) - burada gerçek bir bulguya
        // bağlıyoruz.
        $labFieldMismatch = $input['psi']['lab_field_mismatch'] ?? null;
        if (is_array($labFieldMismatch) && ($labFieldMismatch['mismatch'] ?? false) === true) {
            $labMs = $labFieldMismatch['lab_lcp_ms'] ?? null;
            $fieldMs = $labFieldMismatch['field_lcp_ms'] ?? null;
            $add('performance', 'major', 'olası', 'Laboratuvar testi gerçek kullanıcı deneyimini yansıtmıyor olabilir',
                ($labFieldMismatch['note'] ?? 'Laboratuvar testinde LCP iyi görünüyor ama gerçek kullanıcı verisine göre kötü.')
                    . ($labMs !== null && $fieldMs !== null
                        ? sprintf(' (laboratuvar: %d ms, gerçek kullanıcı: %d ms)', (int) $labMs, (int) $fieldMs)
                        : ''),
                'Gerçek kullanıcı koşullarını (yavaş ağ, uzak sunucu konumu, düşük performanslı cihazlar, üçüncü taraf scriptlerin gerçek etkisi) '
                    . 'göz önünde bulundurun - laboratuvar skoru yüksek olsa bile gerçek ziyaretçiler daha yavaş bir deneyim yaşıyor olabilir.',
                1);
        }

        return $findings;
    }
}

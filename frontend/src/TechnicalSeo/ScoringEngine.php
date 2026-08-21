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
            'crawlability_indexability' => $this->scoreCrawlability($input['indexability'] ?? []),
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

    private function scoreCrawlability(array $indexability): array
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

        return ['score' => (int) max(0, min(100, round($score))), 'confidence' => 'kesin'];
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
     *   detail:string, how_to_fix:string, affected_pages:int, priority_score:float
     * }>
     */
    private function buildFindings(array $input, int $totalPages): array
    {
        $findings = [];
        $add = function (string $category, string $severity, string $confidence, string $title, string $detail, string $howToFix, int $affectedPages) use (&$findings, $totalPages): void {
            $severityWeight = self::SEVERITY_WEIGHTS[$severity] ?? 25;
            $confidenceWeight = self::CONFIDENCE_WEIGHTS[$confidence] ?? 0.6;
            $prevalence = min(1.0, $affectedPages / $totalPages);

            $findings[] = [
                'category' => $category,
                'severity' => $severity,
                'confidence' => $confidence,
                'title' => $title,
                'detail' => $detail,
                'how_to_fix' => $howToFix,
                'affected_pages' => $affectedPages,
                'priority_score' => round($severityWeight * max($prevalence, $affectedPages > 0 ? 0.05 : 0) * $confidenceWeight, 2),
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

        $orphanPages = $indexability['orphan_pages'] ?? [];
        if (!empty($orphanPages)) {
            $add('crawlability_indexability', 'major', 'kesin', count($orphanPages) . ' sayfa sitemap\'te eksik (orphan pages)',
                'Site içinde linklenen ancak sitemap.xml\'de yer almayan sayfalar tespit edildi: ' . implode(', ', array_slice($orphanPages, 0, 5)) . (count($orphanPages) > 5 ? ' ve diğerleri...' : ''),
                'Bu sayfaları sitemap.xml\'e ekleyin ki arama motorları tarafından keşfedilme olasılığı artsın.',
                count($orphanPages));
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
            $add('site_structure_links', 'major', 'kesin', count($brokenLinks) . ' kırık link tespit edildi',
                'Taranan linkler arasında 4xx/5xx durum kodu dönen linkler bulundu.',
                'Kırık linkleri güncelleyin veya kaldırın; taşınan sayfalar için 301 yönlendirmesi ekleyin.',
                count($brokenLinks));
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

        return $findings;
    }
}

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
 */
final class OnPageContentChecker
{
    /**
     * Aynı (normalize edilmiş - baş/son boşluk kırpılmış, küçük harfe
     * çevrilmiş) title etiketini paylaşan sayfa gruplarını bulur. Boş/eksik
     * title'lar bu karşılaştırmaya DAHİL EDİLMEZ - "title hiç yok" ayrı bir
     * sorun, burada sadece "2+ sayfa AYNI title'ı paylaşıyor" aranıyor.
     *
     * @param list<array{url:string, title?:string|null}> $pages
     * @return list<array{value:string, urls:list<string>}>
     */
    public function findDuplicateTitles(array $pages): array
    {
        return $this->findDuplicates($pages, 'title');
    }

    /**
     * @param list<array{url:string, meta_description?:string|null}> $pages
     * @return list<array{value:string, urls:list<string>}>
     */
    public function findDuplicateMetaDescriptions(array $pages): array
    {
        return $this->findDuplicates($pages, 'meta_description');
    }

    /**
     * @param list<array<string,mixed>> $pages
     * @return list<array{value:string, urls:list<string>}>
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

        $duplicates = [];
        foreach ($groups as $group) {
            if (count($group['urls']) > 1) {
                $duplicates[] = $group;
            }
        }

        return array_values($duplicates);
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

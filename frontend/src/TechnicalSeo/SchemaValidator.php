<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * JSON-LD yapılandırılmış veri çıkarımı ve dar kapsamlı şema doğrulaması.
 *
 * Not: Bu sınıf eskiden sayfanın "site tipini" (e-ticaret/kurumsal/blog vb.)
 * çok sinyalli bir sezgisel algoritmayla tahmin edip doğrulama kurallarını
 * buna göre değiştiriyordu. Bu tahmin katmanı kaldırıldı - artık sadece
 * sayfada FİİLEN bulunan yapılandırılmış veriyi (JSON-LD blokları) doğruluyor,
 * sitenin ne tür bir site olduğu konusunda varsayımda bulunmuyor.
 */
final class SchemaValidator
{
    /**
     * Sayfadaki tüm <script type="application/ld+json"> bloklarını çıkarır
     * ve JSON olarak ayrıştırır. @graph içeren bloklar da düzleştirilir.
     *
     * @return list<array<string,mixed>>
     */
    public function extractJsonLd(string $html): array
    {
        $blocks = [];

        if (!preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
            $html,
            $matches
        )) {
            return [];
        }

        foreach ($matches[1] as $rawJson) {
            $decoded = json_decode(trim($rawJson), true);
            if (!is_array($decoded)) {
                continue;
            }

            // Bazı siteler tek bir <script> içine JSON dizisi koyar.
            $items = array_is_list($decoded) ? $decoded : [$decoded];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (isset($item['@graph']) && is_array($item['@graph'])) {
                    foreach ($item['@graph'] as $graphItem) {
                        if (is_array($graphItem)) {
                            $blocks[] = $graphItem;
                        }
                    }
                } else {
                    $blocks[] = $item;
                }
            }
        }

        return $blocks;
    }

    /**
     * Dar kapsamlı şema doğrulaması: sayfada FİİLEN bulunan JSON-LD
     * bloklarının schema.org'a göre zorunlu alanlarını kontrol eder. Sitenin
     * ne tür bir site olduğu konusunda varsayımda bulunmaz - örneğin Product
     * şeması yoksa "olması gerekirdi" demez, sadece varsa eksik alan var mı
     * bakar.
     *
     * @param list<array<string,mixed>> $jsonLdBlocks
     * @return list<array{type:string, severity:string, message:string}>
     */
    public function validate(array $jsonLdBlocks): array
    {
        $issues = [];

        if (empty($jsonLdBlocks)) {
            $issues[] = [
                'type' => 'missing_structured_data',
                'severity' => 'minor',
                'message' => 'Sayfada hiç JSON-LD yapılandırılmış veri bulunamadı.',
            ];
            return $issues;
        }

        $issues = array_merge($issues, $this->validateProductSchema($jsonLdBlocks));

        $hasOrganizationOrWebsite = false;
        foreach ($jsonLdBlocks as $block) {
            $type = $block['@type'] ?? null;
            $types = is_array($type) ? $type : [$type];
            if (array_intersect($types, ['Organization', 'WebSite', 'LocalBusiness'])) {
                $hasOrganizationOrWebsite = true;
                break;
            }
        }

        if (!$hasOrganizationOrWebsite) {
            $issues[] = [
                'type' => 'missing_organization_schema',
                'severity' => 'minor',
                'message' => 'Organization/WebSite şeması bulunamadı (marka bilgisinin arama motorlarınca doğru tanınması için önerilir).',
            ];
        }

        return $issues;
    }

    /**
     * Sayfada FİİLEN bir Product şeması varsa zorunlu alanlarının eksik
     * olup olmadığını kontrol eder. Product şeması hiç yoksa hiçbir şey
     * söylemez - sitenin e-ticaret olup olmadığını artık tahmin etmiyoruz.
     *
     * @param list<array<string,mixed>> $jsonLdBlocks
     * @return list<array{type:string, severity:string, message:string}>
     */
    private function validateProductSchema(array $jsonLdBlocks): array
    {
        $issues = [];
        $productBlocks = array_filter($jsonLdBlocks, static function (array $block): bool {
            $type = $block['@type'] ?? null;
            $types = is_array($type) ? $type : [$type];
            return in_array('Product', $types, true);
        });

        if (empty($productBlocks)) {
            return $issues;
        }

        foreach ($productBlocks as $product) {
            $requiredFields = ['name', 'image', 'offers'];
            foreach ($requiredFields as $field) {
                if (empty($product[$field])) {
                    $issues[] = [
                        'type' => 'incomplete_product_schema',
                        'severity' => 'major',
                        'message' => "Product şemasında zorunlu alan eksik: '{$field}'.",
                    ];
                }
            }

            $offers = $product['offers'] ?? null;
            if (is_array($offers)) {
                $offerFields = ['price', 'priceCurrency', 'availability'];
                $offerToCheck = array_is_list($offers) ? ($offers[0] ?? []) : $offers;
                foreach ($offerFields as $field) {
                    if (is_array($offerToCheck) && empty($offerToCheck[$field])) {
                        $issues[] = [
                            'type' => 'incomplete_offer_schema',
                            'severity' => 'major',
                            'message' => "Offer şemasında zorunlu alan eksik: '{$field}'.",
                        ];
                    }
                }
            }
        }

        return $issues;
    }
}

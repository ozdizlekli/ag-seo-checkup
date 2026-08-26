<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * Kırık link (broken link) taraması. curl_multi ile eşzamanlı (concurrent)
 * çalışır - onlarca linki teker teker sırayla test etmek çok yavaş olurdu.
 */
final class LinkChecker
{
    private Crawler $crawler;
    private int $maxLinks;
    private int $concurrency;

    public function __construct(Crawler $crawler, int $maxLinks = 40, int $concurrency = 10)
    {
        $this->crawler = $crawler;
        $this->maxLinks = $maxLinks;
        $this->concurrency = $concurrency;
    }

    /**
     * @param list<string> $urls
     * @return array{
     *   checked_count: int,
     *   broken: list<array{url:string, status_code:int, error:string|null}>,
     *   ok_count: int,
     *   truncated: bool
     * }
     */
    public function check(array $urls): array
    {
        $urls = array_values(array_unique($urls));
        $truncated = count($urls) > $this->maxLinks;
        $urlsToCheck = array_slice($urls, 0, $this->maxLinks);

        $results = $this->crawler->fetchMultiple($urlsToCheck, $this->concurrency, true);

        $broken = [];
        $okCount = 0;
        $suspects = [];

        foreach ($results as $url => $result) {
            if ($this->isBrokenStatus($result['status_code'])) {
                // Henuz kesin degil - HEAD'e gore "kirik gibi gorunuyor",
                // ama asagida GET ile dogrulanacak (bkz. asagidaki blok).
                $suspects[$url] = $result;
            } else {
                $okCount++;
            }
        }

        // Bazi sunucular/WAF'lar (bot korumasi, ya da sadece HEAD'i hic
        // duzgun desteklememe) HEAD istegine, gercek bir tarayicinin
        // yaptigi GET istegine gore FARKLI davranabiliyor - kullanicinin
        // "kirik" diye isaretlenen bir linki tarayicida acinca sorunsuz
        // yuklendigi gorulduğu icin (HEAD ile 403/
        // baska bir hata donup GET ile 200 donen sunucular var), HEAD'e
        // gore supheli cikan linkleri, tarayicinin yaptigi GIBI bir GET
        // istegiyle IKINCI KEZ kontrol ediyoruz - performans icin butun
        // listeyi degil, sadece supheli olanlari. Ikinci kontrolde de
        // basarisiz olan bir link ARTIK gercekten kirik sayilir.
        if (!empty($suspects)) {
            $recheck = $this->crawler->fetchMultiple(array_keys($suspects), $this->concurrency, false);
            foreach ($suspects as $url => $headResult) {
                $getResult = $recheck[$url] ?? $headResult;
                if ($this->isBrokenStatus($getResult['status_code'])) {
                    $broken[] = [
                        'url' => $url,
                        'status_code' => $getResult['status_code'],
                        'error' => $getResult['error'],
                    ];
                } else {
                    $okCount++;
                }
            }
        }

        return [
            'checked_count' => count($urlsToCheck),
            'broken' => $broken,
            'ok_count' => $okCount,
            'truncated' => $truncated,
        ];
    }

    /**
     * 405 (Method Not Allowed) HEAD isteğini reddeden ama linki kırık
     * olmayan sunucular için hariç tutulur (pratikte doğru bir istisna -
     * bu sunucular GET/normal ziyarette sorunsuz çalışır).
     */
    private function isBrokenStatus(int $status): bool
    {
        return ($status === 0 || $status >= 400) && $status !== 405;
    }
}

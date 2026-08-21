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

        foreach ($results as $url => $result) {
            $status = $result['status_code'];
            // 405 (Method Not Allowed) HEAD isteğini reddeden ama linki
            // kırık olmayan sunucular için hariç tutulur (kızın ve senin
            // kendi projendeki BrokenLinkChecker'da da aynı mantık vardı -
            // pratikte doğru bir istisna).
            $isBroken = ($status === 0 || $status >= 400) && $status !== 405;

            if ($isBroken) {
                $broken[] = [
                    'url' => $url,
                    'status_code' => $status,
                    'error' => $result['error'],
                ];
            } else {
                $okCount++;
            }
        }

        return [
            'checked_count' => count($urlsToCheck),
            'broken' => $broken,
            'ok_count' => $okCount,
            'truncated' => $truncated,
        ];
    }
}

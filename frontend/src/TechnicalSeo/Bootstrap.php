<?php

declare(strict_types=1);

/**
 * Teknik SEO modülü için basit önyükleme dosyası.
 *
 * Bu proje Composer kullanmıyor (proje kuralı: pure PHP, framework/paket
 * yöneticisi yok). Bu yüzden burada elle yazılmış, PSR-4'e benzeyen minik
 * bir autoloader var — App\TechnicalSeo\Foo sınıfı çağrıldığında
 * src/TechnicalSeo/Foo.php dosyasını otomatik olarak dahil eder.
 *
 * Ayrıca .env dosyasını okuyup PSI_API_KEY gibi değerleri $_ENV'e yükler.
 * gemini_proxy.php dosyasındaki basit .env okuma mantığıyla aynı deseni
 * kullanıyoruz ki proje genelinde tutarlı olsun.
 *
 * Kullanım (api/technical_seo_audit.php içinde):
 *   $config = require __DIR__ . '/../src/TechnicalSeo/Bootstrap.php';
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\TechnicalSeo\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

/**
 * .env dosyasını okur (varsa). gemini_proxy.php ile aynı basit format:
 * KEY=VALUE, # ile başlayan satırlar yorum sayılır.
 */
if (!function_exists('technicalSeoLoadEnv')) {
    function technicalSeoLoadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($name !== '' && !isset($_ENV[$name])) {
                $_ENV[$name] = $value;
            }
        }
    }
}

technicalSeoLoadEnv(__DIR__ . '/../../.env');

/**
 * Ortam değişkenini oku: önce $_ENV, sonra sistem ortamı, sonra varsayılan.
 */
if (!function_exists('technicalSeoEnv')) {
    function technicalSeoEnv(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $fromSystem = getenv($key);
        if ($fromSystem !== false && $fromSystem !== '') {
            return $fromSystem;
        }

        return $default;
    }
}

return [
    'psi' => [
        'api_key' => technicalSeoEnv('PAGESPEED_API_KEY', ''),
        'endpoint' => 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
        'timeout' => 60,
    ],
    'http' => [
        'user_agent_desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        // Googlebot Smartphone UA - mobil-öncelikli indeksleme (mobile-first
        // indexing) karşılaştırması için gerçek Google mobil botunu taklit eder.
        'user_agent_mobile' => 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36 '
            . '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'timeout' => 12,
        'max_redirects' => 6,
    ],
    'crawl' => [
        // Standart mod: her analizde otomatik çalışır.
        'max_pages' => 100,
        'max_depth' => 6,
        'max_time_seconds' => 60,
        'concurrency' => 10,
        // "Tüm siteyi tara" modu: kullanıcı standart mod kesildiğinde (truncated)
        // açıkça onay verirse ikinci bir istek (resume_state ile) bu limitlerle çalışır.
        'full_max_pages' => 2000,
        'full_max_depth' => 20,
        'full_max_time_seconds' => 90,
        'max_links_to_check' => 40,
        'link_check_concurrency' => 10,
    ],
];

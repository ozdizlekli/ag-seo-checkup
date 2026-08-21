<?php

declare(strict_types=1);

namespace App\TechnicalSeo;

/**
 * Saf PHP ile SSL/TLS sertifika denetimi.
 *
 * Neden SSL Labs API'sini kullanmıyoruz? SSL Labs'ın API kullanım şartları
 * ("Terms of Use") ticari/otomatik kullanım için ek izin istiyor ve tam bir
 * tarama 60-90 saniye sürebiliyor - bizim "URL yapıştır, hemen sonuç al"
 * modelimizle uyuşmuyor. Bunun yerine PHP'nin kendi openssl eklentisiyle
 * (stream_socket_client + openssl_x509_parse) sertifikayı doğrudan sunucudan
 * çekip kendimiz analiz ediyoruz. Bu, SSL Labs kadar derin değildir (örn.
 * eski protokol/cipher suite zafiyetlerini test etmez) ama "sertifika geçerli
 * mi, ne zaman bitiyor, doğru domain için mi" gibi temel ve en kritik
 * soruları güvenilir şekilde cevaplar.
 */
final class SslChecker
{
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return array{
     *   https_reachable: bool,
     *   valid: bool,
     *   issuer: string|null,
     *   subject: string|null,
     *   valid_from: string|null,
     *   valid_to: string|null,
     *   days_remaining: int|null,
     *   protocol: string|null,
     *   hostname_matches: bool|null,
     *   error: string|null
     * }
     */
    public function check(string $host, int $port = 443): array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $remote = 'ssl://' . $host . ':' . $port;

        $client = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($client === false) {
            return $this->emptyResult(false, "Bağlantı kurulamadı: {$errstr} (errno {$errno})");
        }

        $params = stream_context_get_params($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $protocol = @stream_get_meta_data($client)['crypto']['protocol'] ?? null;
        fclose($client);

        if ($cert === null) {
            return $this->emptyResult(true, 'Sertifika bilgisi okunamadı.');
        }

        $parsed = openssl_x509_parse($cert);
        if ($parsed === false) {
            return $this->emptyResult(true, 'Sertifika ayrıştırılamadı.');
        }

        $validFrom = $parsed['validFrom_time_t'] ?? null;
        $validTo = $parsed['validTo_time_t'] ?? null;
        $now = time();

        $daysRemaining = $validTo !== null ? (int) floor(($validTo - $now) / 86400) : null;
        $isExpired = $validTo !== null && $validTo < $now;
        $isNotYetValid = $validFrom !== null && $validFrom > $now;

        $hostnameMatches = $this->matchesHostname($host, $parsed);

        return [
            'https_reachable' => true,
            'valid' => !$isExpired && !$isNotYetValid && $hostnameMatches,
            'issuer' => $this->flattenDn($parsed['issuer'] ?? []),
            'subject' => $this->flattenDn($parsed['subject'] ?? []),
            'valid_from' => $validFrom !== null ? date('Y-m-d', $validFrom) : null,
            'valid_to' => $validTo !== null ? date('Y-m-d', $validTo) : null,
            'days_remaining' => $daysRemaining,
            'protocol' => is_string($protocol) ? $protocol : null,
            'hostname_matches' => $hostnameMatches,
            'error' => null,
        ];
    }

    /**
     * @param array<string,mixed> $parsed openssl_x509_parse() çıktısı
     */
    private function matchesHostname(string $host, array $parsed): bool
    {
        $names = [];

        $cn = $parsed['subject']['CN'] ?? null;
        if (is_string($cn)) {
            $names[] = $cn;
        }

        $altName = $parsed['extensions']['subjectAltName'] ?? null;
        if (is_string($altName)) {
            foreach (explode(',', $altName) as $entry) {
                $entry = trim($entry);
                if (str_starts_with($entry, 'DNS:')) {
                    $names[] = substr($entry, 4);
                }
            }
        }

        foreach ($names as $name) {
            if ($this->wildcardMatch($name, $host)) {
                return true;
            }
        }

        return false;
    }

    private function wildcardMatch(string $pattern, string $host): bool
    {
        $pattern = strtolower(trim($pattern));
        $host = strtolower(trim($host));

        if ($pattern === $host) {
            return true;
        }

        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1); // ".example.com"
            return str_ends_with($host, $suffix) && substr_count($host, '.') === substr_count($pattern, '.');
        }

        return false;
    }

    /**
     * @param array<string,mixed> $dn
     */
    private function flattenDn(array $dn): string
    {
        $parts = [];
        foreach ($dn as $key => $value) {
            if (is_string($value)) {
                $parts[] = "{$key}={$value}";
            }
        }
        return implode(', ', $parts);
    }

    /**
     * @return array{https_reachable:bool, valid:bool, issuer:null, subject:null, valid_from:null, valid_to:null, days_remaining:null, protocol:null, hostname_matches:null, error:string}
     */
    private function emptyResult(bool $reachable, string $error): array
    {
        return [
            'https_reachable' => $reachable,
            'valid' => false,
            'issuer' => null,
            'subject' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
            'protocol' => null,
            'hostname_matches' => null,
            'error' => $error,
        ];
    }
}

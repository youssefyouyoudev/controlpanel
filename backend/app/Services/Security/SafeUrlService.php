<?php

namespace App\Services\Security;

use App\Exceptions\OperationBlockedException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SafeUrlService
{
    public function assertSafeHttpUrl(string $url, bool $allowInternal = false): void
    {
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || blank($parts['host'] ?? null)) {
            throw new OperationBlockedException('URL probes require an HTTP or HTTPS URL.');
        }

        $host = trim((string) $parts['host'], '[]');
        if (in_array(strtolower($host), ['localhost', 'ip6-localhost', 'ip6-loopback'], true)) {
            throw new OperationBlockedException('URL probes cannot target localhost.');
        }

        if ($allowInternal) {
            return;
        }

        foreach ($this->resolvedAddresses($host) as $ip) {
            if ($this->isPrivateAddress($ip)) {
                throw new OperationBlockedException('URL probes cannot target private, local, reserved or metadata addresses.');
            }
        }
    }

    public function get(string $url, bool $allowInternal = false, int $timeoutSeconds = 5, int $maxRedirects = 2): Response
    {
        $current = $url;

        for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
            $this->assertSafeHttpUrl($current, $allowInternal);

            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout($timeoutSeconds)
                ->withOptions(['allow_redirects' => false, 'stream' => false])
                ->get($current);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $location = $response->header('Location');
            if (! is_string($location) || $location === '') {
                return $response;
            }

            $current = $this->resolveRedirect($current, $location);
        }

        throw new OperationBlockedException('URL probe exceeded the allowed redirect limit.');
    }

    /**
     * @return array<int, string>
     */
    private function resolvedAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$this->normalizeIp($host)];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $addresses = [];
        foreach ($records as $record) {
            foreach (['ip', 'ipv6'] as $key) {
                if (isset($record[$key]) && is_string($record[$key])) {
                    $addresses[] = $this->normalizeIp($record[$key]);
                }
            }
        }

        $fallback = gethostbyname($host);
        if ($fallback !== $host) {
            $addresses[] = $this->normalizeIp($fallback);
        }

        return array_values(array_unique(array_filter($addresses)));
    }

    private function normalizeIp(string $ip): string
    {
        if (str_starts_with(strtolower($ip), '::ffff:')) {
            return substr($ip, 7);
        }

        return $ip;
    }

    private function isPrivateAddress(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            || str_starts_with($ip, '169.254.')
            || $ip === '0.0.0.0'
            || $ip === '::'
            || strtolower($ip) === '::1';
    }

    private function resolveRedirect(string $baseUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $path = $base['path'] ?? '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return "{$scheme}://{$host}{$port}{$directory}/{$location}";
    }
}

<?php

namespace App\Services\Discovery;

use Symfony\Component\Process\Process;

class SslInspector
{
    /**
     * @param  array<string, mixed>  $ingress
     * @return array<string, mixed>
     */
    public function inspect(array $ingress): array
    {
        $certificate = $ingress['ssl_certificate_path'] ?? null;
        $originTls = (bool) ($ingress['ssl_enabled'] ?? false);

        return [
            'origin_tls' => [
                'enabled' => $originTls,
                'certificate_path' => $certificate,
                'expires_at' => is_string($certificate) ? $this->certificateExpiration($certificate) : null,
            ],
            'public_https' => [
                'state' => $originTls ? 'configured' : 'unknown',
                'reason' => $originTls ? 'Nginx listens with TLS or has an SSL certificate configured.' : 'No origin TLS found in the Nginx block.',
            ],
            'proxy' => [
                'cloudflare' => 'unknown',
                'mode' => ($ingress['proxy_destination'] ?? null) ? 'reverse-proxy' : 'direct-origin',
            ],
        ];
    }

    private function certificateExpiration(string $certificate): ?string
    {
        if (! is_file($certificate) || ! is_readable($certificate) || ! $this->binaryExists('openssl')) {
            return null;
        }

        $process = new Process(['openssl', 'x509', '-enddate', '-noout', '-in', $certificate]);
        $process->setTimeout(5);
        $process->run();

        if (preg_match('/notAfter=(.+)$/', trim($process->getOutput().$process->getErrorOutput()), $matches) !== 1) {
            return null;
        }

        $timestamp = strtotime($matches[1]);

        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }

    private function binaryExists(string $binary): bool
    {
        $paths = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));
        $extensions = PHP_OS_FAMILY === 'Windows'
            ? array_filter(explode(';', (string) getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD'))
            : [''];

        foreach (array_unique([...$paths, '/usr/local/sbin', '/usr/local/bin', '/usr/sbin', '/usr/bin', '/sbin', '/bin']) as $path) {
            foreach ($extensions as $extension) {
                if (is_executable(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary.$extension)) {
                    return true;
                }
            }
        }

        return false;
    }
}

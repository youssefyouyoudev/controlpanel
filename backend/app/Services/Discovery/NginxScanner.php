<?php

namespace App\Services\Discovery;

class NginxScanner
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function scan(): array
    {
        $entries = [];

        foreach ($this->configurationFiles() as $path) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            foreach ($this->discoverFromConfig($path, $contents) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function discoverFromConfig(string $path, string $contents): array
    {
        $entries = [];

        foreach ($this->serverBlocks($contents) as $block) {
            $entry = $this->entryFromBlock($path, $block);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function configurationFiles(): array
    {
        $paths = [];

        foreach ((array) config('youpanel.discovery.nginx_paths', []) as $configuredPath) {
            $configuredPath = (string) $configuredPath;

            if (is_file($configuredPath) && is_readable($configuredPath)) {
                $paths[] = realpath($configuredPath) ?: $configuredPath;

                continue;
            }

            if (! is_dir($configuredPath) || ! is_readable($configuredPath)) {
                continue;
            }

            foreach (scandir($configuredPath) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = rtrim($configuredPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$item;
                $real = realpath($path) ?: $path;

                if (is_file($real) && is_readable($real)) {
                    $paths[] = $real;
                }
            }
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    /**
     * @return array<int, string>
     */
    private function serverBlocks(string $contents): array
    {
        $blocks = [];
        $offset = 0;

        while (preg_match('/\bserver\s*\{/i', $contents, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = (int) $match[0][1];
            $brace = strpos($contents, '{', $start);
            if ($brace === false) {
                break;
            }

            $depth = 1;
            $length = strlen($contents);
            for ($index = $brace + 1; $index < $length; $index++) {
                if ($contents[$index] === '{') {
                    $depth++;

                    continue;
                }

                if ($contents[$index] !== '}') {
                    continue;
                }

                $depth--;
                if ($depth === 0) {
                    $blocks[] = substr($contents, $brace + 1, $index - $brace - 1);
                    $offset = $index + 1;

                    continue 2;
                }
            }

            break;
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entryFromBlock(string $path, string $block): ?array
    {
        $serverNames = $this->serverNames($this->directives($block, 'server_name'));
        $listen = $this->directives($block, 'listen');
        $documentRoot = $this->firstDirective($block, 'root');
        $proxyPass = $this->firstDirective($block, 'proxy_pass');
        $certificate = $this->firstDirective($block, 'ssl_certificate');

        if ($serverNames === [] && $documentRoot === null && $proxyPass === null) {
            return null;
        }

        $primaryDomain = $this->primaryDomain($serverNames);

        return [
            'source' => 'nginx',
            'source_path' => realpath($path) ?: $path,
            'primary_domain' => $primaryDomain,
            'domain_aliases' => array_values(array_filter($serverNames, fn (string $name): bool => $name !== $primaryDomain)),
            'server_names' => $serverNames,
            'document_root' => $documentRoot,
            'proxy_destination' => $proxyPass,
            'listen_ports' => $this->listenPorts($listen),
            'http_enabled' => $this->httpEnabled($listen),
            'https_enabled' => $this->sslEnabled($listen, $certificate),
            'ssl_enabled' => $this->sslEnabled($listen, $certificate),
            'ssl_certificate_path' => $certificate,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function directives(string $block, string $directive): array
    {
        preg_match_all('/^\s*'.preg_quote($directive, '/').'\s+(.+?);/mi', $this->stripComments($block), $matches);

        return array_values(array_map(fn (string $value): string => trim($value), $matches[1] ?? []));
    }

    private function firstDirective(string $block, string $directive): ?string
    {
        return $this->directives($block, $directive)[0] ?? null;
    }

    private function stripComments(string $contents): string
    {
        return preg_replace('/^\s*#.*$/m', '', $contents) ?? $contents;
    }

    /**
     * @param  array<int, string>  $directives
     * @return array<int, string>
     */
    private function serverNames(array $directives): array
    {
        $names = [];

        foreach ($directives as $directive) {
            foreach (preg_split('/\s+/', $directive) ?: [] as $name) {
                $name = strtolower(trim($name));
                if ($name === '' || $name === '_' || str_starts_with($name, '$')) {
                    continue;
                }

                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<int, string>  $serverNames
     */
    private function primaryDomain(array $serverNames): ?string
    {
        foreach ($serverNames as $name) {
            if (! str_contains($name, '*')) {
                return $name;
            }
        }

        return $serverNames[0] ?? null;
    }

    /**
     * @param  array<int, string>  $listen
     * @return array<int, int>
     */
    private function listenPorts(array $listen): array
    {
        $ports = [];

        foreach ($listen as $directive) {
            foreach (preg_split('/\s+/', $directive) ?: [] as $part) {
                if (preg_match('/(?::|^)(\d+)$/', $part, $matches) === 1) {
                    $ports[] = (int) $matches[1];
                    break;
                }
            }
        }

        return array_values(array_unique($ports));
    }

    /**
     * @param  array<int, string>  $listen
     */
    private function httpEnabled(array $listen): bool
    {
        $ports = $this->listenPorts($listen);

        return $ports === [] || in_array(80, $ports, true);
    }

    /**
     * @param  array<int, string>  $listen
     */
    private function sslEnabled(array $listen, ?string $certificate): bool
    {
        return $certificate !== null
            || collect($listen)->contains(fn (string $directive): bool => str_contains(strtolower($directive), 'ssl'))
            || in_array(443, $this->listenPorts($listen), true);
    }
}

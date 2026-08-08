<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class NginxWebsiteDiscoveryService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function scan(): array
    {
        $sites = [];

        foreach ($this->configurationFiles() as $path) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            foreach ($this->discoverFromConfig($path, $contents) as $site) {
                $sites[$site['stable_id']] = $site;
            }
        }

        return array_values($sites);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function discoverFromConfig(string $path, string $contents): array
    {
        $sites = [];

        foreach ($this->serverBlocks($contents) as $block) {
            $site = $this->siteFromBlock($path, $block);
            if ($site !== null) {
                $sites[] = $site;
            }
        }

        return $sites;
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
    private function siteFromBlock(string $path, string $block): ?array
    {
        $serverNames = $this->serverNames($this->directives($block, 'server_name'));
        $listen = $this->directives($block, 'listen');
        $documentRoot = $this->firstDirective($block, 'root');
        $applicationRoot = $this->applicationRoot($documentRoot);
        $proxyPass = $this->firstDirective($block, 'proxy_pass');
        $certificate = $this->firstDirective($block, 'ssl_certificate');

        if ($serverNames === [] && $documentRoot === null && $proxyPass === null) {
            return null;
        }

        $primaryDomain = $this->primaryDomain($serverNames);
        $application = $this->detectApplication($applicationRoot, $proxyPass);
        $git = $applicationRoot ? $this->gitMetadata($applicationRoot) : [];
        $health = $this->health($primaryDomain, $this->sslEnabled($listen, $certificate));
        $stableMaterial = implode('|', [realpath($path) ?: $path, implode(',', $serverNames), $documentRoot ?? '', $proxyPass ?? '']);

        return [
            'stable_id' => 'nginx:'.hash('sha256', $stableMaterial),
            'source' => 'nginx',
            'source_path' => realpath($path) ?: $path,
            'name' => $this->nameFor($primaryDomain, $applicationRoot, $proxyPass),
            'primary_domain' => $primaryDomain,
            'domain_aliases' => array_values(array_filter($serverNames, fn (string $name): bool => $name !== $primaryDomain)),
            'server_names' => $serverNames,
            'root_path' => $applicationRoot,
            'document_root' => $documentRoot,
            'proxy_destination' => $proxyPass,
            'listen_ports' => $this->listenPorts($listen),
            'http_enabled' => $this->httpEnabled($listen),
            'https_enabled' => $this->sslEnabled($listen, $certificate),
            'ssl_enabled' => $this->sslEnabled($listen, $certificate),
            'ssl_certificate_path' => $certificate,
            'ssl_expires_at' => $certificate ? $this->certificateExpiration($certificate) : null,
            'http_status' => $health['status_code'],
            'response_time_ms' => $health['response_time_ms'],
            'health_state' => $this->healthState($health['status_code']),
            'application_type' => $application['type'],
            'stack' => $application['stack'],
            'runtime' => $application['runtime'],
            'php_version' => str_contains(strtolower((string) $application['runtime']), 'php') ? $this->version(['php', '-r', 'echo PHP_VERSION;']) : null,
            'node_version' => str_contains(strtolower((string) $application['runtime']), 'node') ? $this->version(['node', '--version']) : null,
            'directory_size_bytes' => $applicationRoot ? $this->directorySize($applicationRoot) : null,
            'git' => $git,
            'git_branch' => $git['branch'] ?? null,
            'last_commit' => $git['last_commit'] ?? null,
            'runtime_association' => $this->runtimeAssociation($application, $proxyPass),
            'discovered_at' => now()->toISOString(),
        ];
    }

    private function applicationRoot(?string $documentRoot): ?string
    {
        if ($documentRoot === null) {
            return null;
        }

        $root = rtrim($documentRoot, DIRECTORY_SEPARATOR);
        $parent = dirname($root);

        if (basename($root) === 'public' && (is_file($parent.DIRECTORY_SEPARATOR.'artisan') || is_file($parent.DIRECTORY_SEPARATOR.'composer.json') || is_file($parent.DIRECTORY_SEPARATOR.'package.json'))) {
            return $parent;
        }

        return $root;
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

    private function nameFor(?string $domain, ?string $root, ?string $proxyPass): string
    {
        if ($domain) {
            return Str::headline(str_replace(['.', '-'], ' ', preg_replace('/^www\./', '', $domain) ?? $domain));
        }

        if ($root) {
            return basename($root);
        }

        return parse_url((string) $proxyPass, PHP_URL_HOST) ?: 'Discovered Website';
    }

    /**
     * @return array{type: string, stack: string, runtime: string|null}
     */
    private function detectApplication(?string $root, ?string $proxyPass): array
    {
        if ($root === null || ! is_dir($root) || ! is_readable($root)) {
            return $proxyPass
                ? ['type' => 'reverse_proxy', 'stack' => 'Reverse proxy', 'runtime' => null]
                : ['type' => 'unknown', 'stack' => 'Unknown', 'runtime' => null];
        }

        $has = fn (string $file): bool => file_exists(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file);
        $package = $this->packageJson($root);

        return match (true) {
            $has('artisan') && $has('composer.json') => ['type' => 'laravel', 'stack' => 'Laravel', 'runtime' => 'PHP'],
            $has('wp-config.php') => ['type' => 'wordpress', 'stack' => 'WordPress', 'runtime' => 'PHP'],
            $has('next.config.js') || $has('next.config.mjs') || $has('next.config.ts') => ['type' => 'nextjs', 'stack' => 'Next.js', 'runtime' => 'Node'],
            $has('Dockerfile') || $has('docker-compose.yml') || $has('docker-compose.yaml') => ['type' => 'docker', 'stack' => 'Docker application', 'runtime' => 'Docker'],
            $has('vite.config.js') || $has('vite.config.ts') => ['type' => $this->viteType($package), 'stack' => $this->viteStack($package), 'runtime' => 'Node'],
            $package !== [] => ['type' => 'node', 'stack' => 'Node.js', 'runtime' => 'Node'],
            $has('public/index.php') || $this->containsPhpFiles($root) => ['type' => 'php', 'stack' => 'PHP', 'runtime' => 'PHP'],
            $has('index.html') || $has('public/index.html') => ['type' => 'static', 'stack' => 'Static HTML', 'runtime' => null],
            default => ['type' => $proxyPass ? 'reverse_proxy' : 'unknown', 'stack' => $proxyPass ? 'Reverse proxy' : 'Unknown', 'runtime' => null],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function packageJson(string $root): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'package.json';
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 512 * 1024) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function viteType(array $package): string
    {
        $dependencies = json_encode([$package['dependencies'] ?? [], $package['devDependencies'] ?? []]) ?: '';

        return match (true) {
            str_contains($dependencies, 'vue') => 'vue',
            str_contains($dependencies, 'react') => 'react',
            default => 'vite',
        };
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function viteStack(array $package): string
    {
        return match ($this->viteType($package)) {
            'vue' => 'Vue / Vite',
            'react' => 'React / Vite',
            default => 'Vite',
        };
    }

    private function containsPhpFiles(string $root): bool
    {
        return is_file(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'index.php')
            || is_file(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php');
    }

    /**
     * @return array<string, mixed>
     */
    private function gitMetadata(string $root): array
    {
        if (! is_dir(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.git')) {
            return [];
        }

        $commit = $this->run(['git', 'log', '-1', '--pretty=%H%x1f%s%x1f%cI'], $root, true);
        $parts = explode("\x1f", trim($commit));
        $changes = array_values(array_filter(explode("\n", trim($this->run(['git', 'status', '--short'], $root, true)))));

        return [
            'remote_url' => $this->redactRemote($this->run(['git', 'remote', 'get-url', 'origin'], $root, true)),
            'branch' => trim($this->run(['git', 'branch', '--show-current'], $root, true)) ?: null,
            'last_commit' => [
                'hash' => $parts[0] ?? null,
                'message' => $parts[1] ?? null,
                'date' => $parts[2] ?? null,
            ],
            'dirty' => $changes !== [],
        ];
    }

    /**
     * @return array{status_code: int|null, response_time_ms: int|null}
     */
    private function health(?string $primaryDomain, bool $ssl): array
    {
        if (! $primaryDomain || str_contains($primaryDomain, '*') || ! (bool) config('youpanel.discovery.health_checks')) {
            return ['status_code' => null, 'response_time_ms' => null];
        }

        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('youpanel.discovery.health_timeout_seconds', 5))
                ->connectTimeout((int) config('youpanel.discovery.health_timeout_seconds', 5))
                ->withoutRedirecting()
                ->get(($ssl ? 'https://' : 'http://').$primaryDomain);

            return [
                'status_code' => $response->status(),
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        } catch (\Throwable) {
            return ['status_code' => null, 'response_time_ms' => null];
        }
    }

    private function healthState(?int $status): string
    {
        return match (true) {
            $status === null => 'unknown',
            $status >= 200 && $status < 400 => 'healthy',
            $status >= 400 && $status < 500 => 'degraded',
            default => 'offline',
        };
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private function runtimeAssociation(array $application, ?string $proxyPass): ?string
    {
        if ($proxyPass) {
            return 'reverse proxy';
        }

        return match ($application['runtime'] ?? null) {
            'PHP' => 'php-fpm',
            'Node' => 'pm2/systemd',
            'Docker' => 'docker',
            default => null,
        };
    }

    private function certificateExpiration(string $certificate): ?string
    {
        if (! is_file($certificate) || ! is_readable($certificate) || ! $this->binaryExists('openssl')) {
            return null;
        }

        $output = $this->run(['openssl', 'x509', '-enddate', '-noout', '-in', $certificate], null, true);
        if (preg_match('/notAfter=(.+)$/', trim($output), $matches) !== 1) {
            return null;
        }

        $timestamp = strtotime($matches[1]);

        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }

    private function directorySize(string $root): ?int
    {
        if (! is_dir($root) || ! is_readable($root)) {
            return null;
        }

        $size = 0;
        $count = 0;
        $limit = (int) config('youpanel.discovery.directory_size_max_items', 5000);
        $ignored = ['.git', 'node_modules', 'vendor', '.next', 'storage/logs', 'tmp', 'temp'];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', str_replace(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, '', $item->getPathname()));
            if (collect($ignored)->contains(fn (string $path): bool => str_starts_with($relative, $path.'/') || $relative === $path)) {
                continue;
            }

            if ($item->isFile()) {
                $size += $item->getSize();
            }

            if (++$count >= $limit) {
                break;
            }
        }

        return $size;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command, ?string $workingDirectory = null, bool $allowFailure = false): string
    {
        if (! $this->binaryExists($command[0])) {
            return '';
        }

        $process = new Process($command, $workingDirectory);
        $process->setTimeout(5);
        $process->run();

        if (! $allowFailure && ! $process->isSuccessful()) {
            return '';
        }

        return $process->getOutput().$process->getErrorOutput();
    }

    /**
     * @param  array<int, string>  $command
     */
    private function version(array $command): ?string
    {
        $output = trim($this->run($command, null, true));

        return $output === '' ? null : Str::limit(preg_replace('/\s+/', ' ', $output) ?: $output, 120, '');
    }

    private function binaryExists(string $binary): bool
    {
        $paths = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));
        $extensions = PHP_OS_FAMILY === 'Windows'
            ? array_filter(explode(PATH_SEPARATOR, (string) getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD'))
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

    private function redactRemote(string $remote): ?string
    {
        $remote = trim($remote);
        if ($remote === '') {
            return null;
        }

        return preg_replace('/\/\/[^@\s]+@/', '//[redacted]@', $remote) ?: $remote;
    }
}

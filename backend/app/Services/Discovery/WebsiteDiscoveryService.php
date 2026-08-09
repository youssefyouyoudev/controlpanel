<?php

namespace App\Services\Discovery;

use App\Exceptions\OperationBlockedException;
use App\Services\AuditLogger;
use App\Services\Security\SafeUrlService;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class WebsiteDiscoveryService
{
    public function __construct(
        private readonly NginxScanner $nginx,
        private readonly ProjectRootResolver $roots,
        private readonly StackDetector $stacks,
        private readonly GitInspector $git,
        private readonly ProcessInspector $processes,
        private readonly SslInspector $ssl,
        private readonly DatabaseDetector $databases,
        private readonly StorageInspector $storage,
        private readonly SafeUrlService $urls,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function scan(): array
    {
        $sites = [];

        foreach ($this->nginx->scan() as $ingress) {
            $site = $this->siteFromIngress($ingress);
            $sites[$site['stable_id']] = $site;
        }

        return array_values($sites);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function discoverFromConfig(string $path, string $contents): array
    {
        return array_map(fn (array $ingress): array => $this->siteFromIngress($ingress), $this->nginx->discoverFromConfig($path, $contents));
    }

    /**
     * @param  array<string, mixed>  $ingress
     * @return array<string, mixed>
     */
    private function siteFromIngress(array $ingress): array
    {
        $root = $this->roots->resolve($ingress['document_root'] ?? null, $ingress['proxy_destination'] ?? null);
        $rootPath = $root['root_path'];
        $stack = $this->stacks->detect($rootPath, $ingress['proxy_destination'] ?? null);
        $git = $this->git->inspect($rootPath);
        $ssl = $this->ssl->inspect($ingress);
        $databaseConnections = $this->databases->detect($rootPath, $stack['components'] ?? []);
        $processes = $this->processes->inspect($rootPath, $ingress['proxy_destination'] ?? null);
        $health = $this->health($ingress['primary_domain'] ?? null, (bool) ($ingress['ssl_enabled'] ?? false), $processes);
        $stableMaterial = implode('|', [$ingress['source_path'] ?? '', implode(',', $ingress['server_names'] ?? []), $rootPath ?? '', $ingress['proxy_destination'] ?? '']);

        return [
            'stable_id' => 'nginx:'.hash('sha256', $stableMaterial),
            ...$ingress,
            'name' => $this->nameFor($ingress['primary_domain'] ?? null, $rootPath, $ingress['proxy_destination'] ?? null),
            'root_path' => $rootPath,
            'project_root_evidence' => $root['evidence'],
            'ssl_expires_at' => data_get($ssl, 'origin_tls.expires_at'),
            'http_status' => $health['status_code'],
            'response_time_ms' => $health['response_time_ms'],
            'health_state' => $health['state'],
            'application_type' => $stack['primary_type'],
            'stack' => $stack['summary'],
            'runtime' => $stack['primary_runtime'],
            'php_version' => in_array('PHP', $stack['runtimes'] ?? [], true) ? $this->phpVersion() : null,
            'node_version' => in_array('Node', $stack['runtimes'] ?? [], true) ? $this->nodeVersion() : null,
            'directory_size_bytes' => $this->storage->directorySize($rootPath),
            'git' => $git,
            'git_branch' => $git['branch'] ?? null,
            'last_commit' => $git['last_commit'] ?? null,
            'runtime_association' => $this->runtimeAssociation($stack, $ingress['proxy_destination'] ?? null, $processes),
            'project' => [
                'root_path' => $rootPath,
                'document_root' => $ingress['document_root'] ?? null,
                'architecture' => $stack['architecture'],
                'frameworks' => $stack['frameworks'],
                'runtimes' => $stack['runtimes'],
                'components' => $stack['components'],
                'processes' => $processes,
                'ssl' => $ssl,
                'databases' => $databaseConnections,
                'git' => $git,
                'storage' => [
                    'size_bytes' => $this->storage->directorySize($rootPath),
                    'excluded' => config('youpanel.discovery.size_exclude', []),
                ],
                'evidence' => array_values(array_unique([...$root['evidence'], ...($stack['evidence'] ?? [])])),
            ],
            'databases' => $databaseConnections,
            'discovered_at' => now()->toISOString(),
        ];
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
     * @param  array<string, mixed>  $processes
     * @return array{status_code: int|null, response_time_ms: int|null, state: string}
     */
    private function health(?string $primaryDomain, bool $ssl, array $processes): array
    {
        $http = ['status_code' => null, 'response_time_ms' => null];

        if ($primaryDomain && ! str_contains($primaryDomain, '*') && (bool) config('youpanel.discovery.health_checks')) {
            $startedAt = microtime(true);

            try {
                $url = ($ssl ? 'https://' : 'http://').$primaryDomain;
                $response = $this->urls->get(
                    $url,
                    (bool) config('youpanel.discovery.allow_internal_http', false),
                    (int) config('youpanel.discovery.health_timeout_seconds', 5),
                    2,
                );

                $http = [
                    'status_code' => $response->status(),
                    'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ];
            } catch (OperationBlockedException $exception) {
                $this->auditLogger->record('discovery.blocked_ssrf', null, null, [
                    'target_type' => 'website_discovery',
                    'target_identifier' => $primaryDomain,
                    'reason' => $exception->getMessage(),
                ]);
                $http = ['status_code' => null, 'response_time_ms' => null];
            } catch (\Throwable) {
                $http = ['status_code' => null, 'response_time_ms' => null];
            }
        }

        $state = $this->healthState($http['status_code']);
        if ($state === 'unknown' && collect($processes['pm2'] ?? [])->contains(fn (array $process): bool => ($process['status'] ?? null) === 'online')) {
            $state = 'healthy';
        }
        if ($state === 'unknown' && data_get($processes, 'php_fpm.status') === 'active') {
            $state = 'healthy';
        }

        return [...$http, 'state' => $state];
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
     * @param  array<string, mixed>  $stack
     * @param  array<string, mixed>  $processes
     */
    private function runtimeAssociation(array $stack, ?string $proxyPass, array $processes): ?string
    {
        if ($proxyPass) {
            return collect($processes['pm2'] ?? [])->isNotEmpty() ? 'pm2 reverse proxy' : 'reverse proxy';
        }

        $runtimes = $stack['runtimes'] ?? [];

        return match (true) {
            in_array('PHP', $runtimes, true) && data_get($processes, 'php_fpm.available') => 'php-fpm',
            in_array('Node', $runtimes, true) && collect($processes['pm2'] ?? [])->isNotEmpty() => 'pm2',
            in_array('Node', $runtimes, true) => 'node/systemd',
            data_get($processes, 'docker.compose_file') => 'docker compose',
            default => null,
        };
    }

    private function phpVersion(): ?string
    {
        return PHP_VERSION;
    }

    private function nodeVersion(): ?string
    {
        try {
            $process = new Process(['node', '--version']);
            $process->setTimeout(5);
            $process->run();
            $output = trim($process->getOutput().$process->getErrorOutput());
        } catch (\Throwable) {
            return null;
        }

        return $process->isSuccessful() && $output !== '' ? Str::limit($output, 120, '') : null;
    }
}

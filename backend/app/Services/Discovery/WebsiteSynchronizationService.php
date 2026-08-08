<?php

namespace App\Services\Discovery;

use App\Enums\ServerStatus;
use App\Enums\WebsiteComponentType;
use App\Enums\WebsiteStatus;
use App\Models\AllowedPath;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

class WebsiteSynchronizationService
{
    public function __construct(
        private readonly NginxWebsiteDiscoveryService $discovery,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{created: int, updated: int, unchanged: int, websites: array<int, Website>, discovered: array<int, array<string, mixed>>}
     */
    public function synchronize(User $user): array
    {
        $server = $this->localServer();
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $websites = [];
        $discovered = $this->discovery->scan();

        foreach ($discovered as $site) {
            $website = $this->matchWebsite($site);
            $exists = $website !== null;
            $website ??= new Website;

            $attributes = $this->websiteAttributes($server, $site, $website);
            $before = $website->exists ? $website->only(array_keys($attributes)) + ['metadata' => $website->metadata] : [];
            $website->fill($attributes);
            $website->save();

            $this->syncAllowedPath($website, $site);
            $this->syncComponent($website, $site);

            if (! $exists) {
                $created++;
            } elseif ($before !== ($website->only(array_keys($attributes)) + ['metadata' => $website->metadata])) {
                $updated++;
            } else {
                $unchanged++;
            }

            $websites[] = $website->refresh();
        }

        $this->auditLogger->record('websites.synchronized', $user, null, [
            'target_type' => 'server',
            'target_identifier' => $server->slug,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'discovered' => count($discovered),
        ]);

        return compact('created', 'updated', 'unchanged', 'websites', 'discovered');
    }

    private function localServer(): Server
    {
        return Server::query()->where('is_local', true)->first()
            ?? Server::query()->firstOrCreate(
                ['slug' => 'local-server'],
                [
                    'name' => 'Local Server',
                    'hostname' => gethostname() ?: 'localhost',
                    'operating_system' => PHP_OS_FAMILY,
                    'is_local' => true,
                    'status' => ServerStatus::Healthy,
                    'last_seen_at' => now(),
                ]
            );
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function matchWebsite(array $site): ?Website
    {
        $stableId = $site['stable_id'];

        $matchedByStableId = Website::query()
            ->get()
            ->first(fn (Website $website): bool => data_get($website->metadata, 'discovery.stable_id') === $stableId);

        if ($matchedByStableId) {
            return $matchedByStableId;
        }

        if ($site['primary_domain']) {
            $matchedByDomain = Website::query()->where('domain', $site['primary_domain'])->first();
            if ($matchedByDomain) {
                return $matchedByDomain;
            }
        }

        if ($site['root_path']) {
            return Website::query()->where('root_path', $site['root_path'])->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function websiteAttributes(Server $server, array $site, Website $website): array
    {
        $metadata = array_replace_recursive($website->metadata ?? [], [
            'discovery' => [
                'stable_id' => $site['stable_id'],
                'source' => $site['source'],
                'source_path' => $site['source_path'],
                'domain_aliases' => $site['domain_aliases'],
                'server_names' => $site['server_names'],
                'document_root' => $site['document_root'],
                'proxy_destination' => $site['proxy_destination'],
                'listen_ports' => $site['listen_ports'],
                'http_enabled' => $site['http_enabled'],
                'https_enabled' => $site['https_enabled'],
                'ssl_enabled' => $site['ssl_enabled'],
                'ssl_certificate_path' => $site['ssl_certificate_path'],
                'ssl_expires_at' => $site['ssl_expires_at'],
                'http_status' => $site['http_status'],
                'response_time_ms' => $site['response_time_ms'],
                'application_type' => $site['application_type'],
                'stack' => $site['stack'],
                'runtime' => $site['runtime'],
                'php_version' => $site['php_version'],
                'node_version' => $site['node_version'],
                'directory_size_bytes' => $site['directory_size_bytes'],
                'git' => $site['git'],
                'runtime_association' => $site['runtime_association'],
                'discovered_at' => $site['discovered_at'],
            ],
        ]);

        return [
            'server_id' => $server->id,
            'name' => $website->exists ? $website->name : $site['name'],
            'slug' => $website->exists ? $website->slug : $this->uniqueSlug($site),
            'domain' => $site['primary_domain'] ?? $website->domain,
            'framework' => $site['stack'],
            'root_path' => $site['root_path'] ?: ($website->root_path ?: dirname((string) $site['source_path'])),
            'repository_url' => data_get($site, 'git.remote_url') ?: $website->repository_url,
            'repository_branch' => $site['git_branch'] ?: ($website->repository_branch ?: 'main'),
            'status' => $this->websiteStatus((string) $site['health_state']),
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function uniqueSlug(array $site): string
    {
        $base = Str::slug((string) ($site['primary_domain'] ?? $site['name'] ?? 'website')) ?: 'website';
        $slug = $base;
        $index = 2;

        while (Website::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    private function websiteStatus(string $state): WebsiteStatus
    {
        return match ($state) {
            'healthy' => WebsiteStatus::Healthy,
            'degraded' => WebsiteStatus::Degraded,
            'offline' => WebsiteStatus::Offline,
            default => WebsiteStatus::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function syncAllowedPath(Website $website, array $site): void
    {
        $root = $site['root_path'];
        if (! is_string($root) || ! is_dir($root) || ! is_readable($root)) {
            return;
        }

        AllowedPath::query()->updateOrCreate(
            [
                'website_id' => $website->id,
                'absolute_path_hash' => hash('sha256', AllowedPath::normalizeRootPath($root)),
                'is_active' => true,
            ],
            [
                'name' => 'Discovered root',
                'relative_label' => 'Nginx document root',
                'absolute_path' => $root,
                'is_primary' => true,
                'can_read' => true,
                'can_write' => false,
                'can_upload' => false,
                'can_create' => false,
                'can_rename' => false,
                'can_move' => false,
                'can_copy' => false,
                'can_delete' => false,
                'can_archive' => true,
                'can_extract' => false,
                'allowed_extensions' => null,
                'blocked_patterns' => ['.env', '.env.*', '*.key', '*.pem', 'id_rsa', 'id_ed25519', 'credentials*', 'secrets*'],
                'metadata' => ['source' => 'nginx-discovery', 'diagnostics' => ['status' => 'readable', 'readable' => true, 'writable' => is_writable($root)]],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function syncComponent(Website $website, array $site): void
    {
        WebsiteComponent::query()->updateOrCreate(
            ['website_id' => $website->id, 'slug' => 'discovered-app'],
            [
                'name' => 'Discovered application',
                'type' => $this->componentType((string) $site['application_type']),
                'relative_working_directory' => '',
                'runtime' => $site['runtime'],
                'process_manager' => $site['runtime_association'],
                'process_name' => null,
                'health_url' => $site['primary_domain'] ? (($site['https_enabled'] ? 'https://' : 'http://').$site['primary_domain']) : null,
                'status' => $this->websiteStatus((string) $site['health_state'])->value,
                'configuration' => [
                    'source' => 'nginx-discovery',
                    'proxy_destination' => $site['proxy_destination'],
                    'application_type' => $site['application_type'],
                ],
                'is_active' => true,
            ]
        );
    }

    private function componentType(string $applicationType): WebsiteComponentType
    {
        return match ($applicationType) {
            'laravel' => WebsiteComponentType::Laravel,
            'nextjs' => WebsiteComponentType::Nextjs,
            'vite', 'react', 'vue' => WebsiteComponentType::Vite,
            'node' => WebsiteComponentType::Node,
            'static' => WebsiteComponentType::Static,
            default => WebsiteComponentType::Custom,
        };
    }
}

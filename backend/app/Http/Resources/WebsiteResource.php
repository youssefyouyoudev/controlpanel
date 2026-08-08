<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'framework' => $this->framework,
            'status' => $this->status?->value,
            'server' => $this->whenLoaded('server', fn (): ServerResource => new ServerResource($this->server)),
            'repository_url' => $this->repository_url,
            'repository_branch' => $this->repository_branch,
            'assigned_port' => $this->when($user?->isOwner(), $this->assigned_port),
            'display_path' => $this->when($user?->isOwner(), $this->root_path),
            'discovery' => [
                'source' => $this->metadata['discovery']['source'] ?? null,
                'source_path' => $this->when($user?->isOwner(), $this->metadata['discovery']['source_path'] ?? null),
                'domain_aliases' => $this->metadata['discovery']['domain_aliases'] ?? [],
                'server_names' => $this->metadata['discovery']['server_names'] ?? [],
                'document_root' => $this->when($user?->isOwner(), $this->metadata['discovery']['document_root'] ?? null),
                'proxy_destination' => $this->metadata['discovery']['proxy_destination'] ?? null,
                'listen_ports' => $this->metadata['discovery']['listen_ports'] ?? [],
                'http_enabled' => $this->metadata['discovery']['http_enabled'] ?? null,
                'https_enabled' => $this->metadata['discovery']['https_enabled'] ?? null,
                'ssl_enabled' => $this->metadata['discovery']['ssl_enabled'] ?? null,
                'ssl_expires_at' => $this->metadata['discovery']['ssl_expires_at'] ?? null,
                'http_status' => $this->metadata['discovery']['http_status'] ?? null,
                'response_time_ms' => $this->metadata['discovery']['response_time_ms'] ?? null,
                'application_type' => $this->metadata['discovery']['application_type'] ?? null,
                'stack' => $this->metadata['discovery']['stack'] ?? null,
                'runtime' => $this->metadata['discovery']['runtime'] ?? null,
                'php_version' => $this->metadata['discovery']['php_version'] ?? null,
                'node_version' => $this->metadata['discovery']['node_version'] ?? null,
                'directory_size_bytes' => $this->metadata['discovery']['directory_size_bytes'] ?? null,
                'git_branch' => $this->metadata['discovery']['git']['branch'] ?? null,
                'git_remote_url' => $this->metadata['discovery']['git']['remote_url'] ?? null,
                'last_commit' => $this->metadata['discovery']['git']['last_commit'] ?? null,
                'git_dirty' => $this->metadata['discovery']['git']['dirty'] ?? null,
                'runtime_association' => $this->metadata['discovery']['runtime_association'] ?? null,
                'discovered_at' => $this->metadata['discovery']['discovered_at'] ?? null,
            ],
            'modules' => [
                'files' => $this->allowedPaths()->where('is_active', true)->exists() ? 'Available' : 'Unavailable',
                'logs' => $this->logSources()->where('is_active', true)->exists() ? 'Available' : 'Needs configuration',
                'deployments' => $this->deployments()->exists() ? 'Available' : 'Needs configuration',
                'backups' => $this->backups()->exists() || $this->backupSchedules()->exists() ? 'Available' : 'Needs configuration',
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

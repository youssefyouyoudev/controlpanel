<?php

namespace App\Services\Coolify;

use App\Services\Operations\SecretRedactor;
use Illuminate\Support\Arr;

class CoolifyResourceMapper
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function normalize(string $type, array $payload): array
    {
        $uuid = (string) ($payload['uuid'] ?? $payload['id'] ?? '');
        $domains = $this->domains($payload);

        return [
            'resource_type' => $type,
            'coolify_uuid' => $uuid,
            'display_name' => (string) ($payload['name'] ?? $payload['fqdn'] ?? $payload['description'] ?? $uuid),
            'status' => (string) ($payload['status'] ?? $payload['applicationStatus'] ?? $payload['databaseStatus'] ?? 'unknown'),
            'project_uuid' => Arr::get($payload, 'project.uuid') ?? $payload['project_uuid'] ?? null,
            'environment_uuid' => Arr::get($payload, 'environment.uuid') ?? $payload['environment_uuid'] ?? null,
            'project' => Arr::get($payload, 'project.name') ?? $payload['project_name'] ?? null,
            'environment' => Arr::get($payload, 'environment.name') ?? $payload['environment_name'] ?? null,
            'domains' => $domains,
            'repository' => $this->safeRepository($payload['git_repository'] ?? $payload['repository_url'] ?? null),
            'branch' => $payload['git_branch'] ?? $payload['branch'] ?? null,
            'image' => $this->redactor->redact((string) ($payload['docker_registry_image_name'] ?? $payload['image'] ?? '')),
            'raw_kind' => $payload['type'] ?? $type,
        ];
    }

    /** @param array<string, mixed> $payload @return array<int, string> */
    private function domains(array $payload): array
    {
        $value = $payload['domains'] ?? $payload['fqdn'] ?? $payload['url'] ?? '';

        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function safeRepository(?string $repository): ?string
    {
        if (! $repository) {
            return null;
        }

        return $this->redactor->redact($repository);
    }
}

<?php

namespace App\Services\Coolify;

use App\Contracts\CoolifyClientInterface;
use App\Exceptions\CoolifyAuthenticationException;
use App\Exceptions\CoolifyConnectionException;
use App\Exceptions\CoolifyRateLimitException;
use App\Exceptions\CoolifyResourceNotFoundException;
use App\Exceptions\CoolifyUnsupportedCapabilityException;
use App\Models\CoolifyResourceLink;
use App\Models\Deployment;
use Illuminate\Support\Str;

class MockCoolifyClient implements CoolifyClientInterface
{
    public function status(): array
    {
        return match (config('coolify.mock_state', env('COOLIFY_MOCK_STATE', 'healthy'))) {
            'offline' => throw new CoolifyConnectionException('Coolify mock is offline.'),
            'auth_failed' => throw new CoolifyAuthenticationException('Coolify token is invalid.'),
            'rate_limited' => throw new CoolifyRateLimitException(retryAfter: 10),
            default => [
                'enabled' => (bool) config('coolify.enabled'),
                'driver' => 'mock',
                'connected' => true,
                'version' => '4.1.2-mock',
                'health' => 'ok',
                'last_successful_connection' => now()->toISOString(),
            ],
        };
    }

    public function capabilities(): array
    {
        return app(CoolifyCapabilityService::class)->capabilities();
    }

    public function resources(?string $type = null): array
    {
        $items = [
            $this->resourceFixture('application', 'mock-app-portfolio', 'Youssef Portfolio', 'running', ['youssefyouyou.com']),
            $this->resourceFixture('application', 'mock-app-rifitv', 'RiFiTV', 'degraded', ['rifitv.com']),
            $this->resourceFixture('service', 'mock-service-redis', 'Portfolio Redis', 'running', []),
            $this->resourceFixture('database', 'mock-db-mysql', 'Portfolio MySQL', 'running', []),
        ];

        return array_values(array_filter($items, fn (array $item): bool => $type === null || $item['resource_type'] === $type));
    }

    public function resource(string $type, string $uuid): ?array
    {
        $resource = collect($this->resources($type))->firstWhere('coolify_uuid', $uuid);

        if (! $resource) {
            throw new CoolifyResourceNotFoundException('Coolify resource was not found.');
        }

        return $resource;
    }

    public function deploy(CoolifyResourceLink $link, Deployment $deployment): array
    {
        if ($link->resource_type->value !== 'application') {
            throw new CoolifyUnsupportedCapabilityException('Only linked Coolify applications can be deployed in Phase 4.');
        }

        $state = (string) ($deployment->metadata['mock_result'] ?? env('COOLIFY_MOCK_DEPLOYMENT_RESULT', 'success'));

        return [
            'deployment_uuid' => $state === 'slow' ? 'mock-deploy-slow' : 'mock-deploy-'.Str::lower(Str::random(8)),
            'status' => $state === 'failed' ? 'failed' : ($state === 'cancelled' ? 'cancelled' : 'queued'),
            'message' => $state === 'failed' ? 'Mock deployment failed.' : 'Mock deployment queued.',
        ];
    }

    public function cancelDeployment(string $deploymentUuid): array
    {
        return ['deployment_uuid' => $deploymentUuid, 'status' => 'cancelled', 'message' => 'Mock deployment cancelled.'];
    }

    public function deployments(?CoolifyResourceLink $link = null): array
    {
        $resourceUuid = $link?->coolify_uuid ?? 'mock-app-portfolio';

        return [
            ['deployment_uuid' => 'mock-deploy-active', 'resource_uuid' => $resourceUuid, 'status' => 'building', 'created_at' => now()->subMinute()->toISOString()],
            ['deployment_uuid' => 'mock-deploy-success', 'resource_uuid' => $resourceUuid, 'status' => 'finished', 'created_at' => now()->subHour()->toISOString()],
        ];
    }

    public function deployment(string $deploymentUuid): ?array
    {
        return ['deployment_uuid' => $deploymentUuid, 'status' => str_contains($deploymentUuid, 'slow') ? 'building' : 'finished'];
    }

    public function deploymentLogs(string $deploymentUuid): array
    {
        return [
            'deployment_uuid' => $deploymentUuid,
            'logs' => "Cloning repository\nInstalling dependencies\nTOKEN=secret-value\nDeployment completed",
            'complete' => ! str_contains($deploymentUuid, 'slow'),
        ];
    }

    public function resourceAction(CoolifyResourceLink $link, string $action): array
    {
        if ($link->resource_type->value !== 'application') {
            throw new CoolifyUnsupportedCapabilityException('Start, stop and restart are only enabled for linked applications in Phase 4.');
        }

        return ['resource_uuid' => $link->coolify_uuid, 'action' => $action, 'status' => 'queued', 'message' => 'Mock resource action queued.'];
    }

    /** @return array<string, mixed> */
    private function resourceFixture(string $type, string $uuid, string $name, string $status, array $domains): array
    {
        return [
            'resource_type' => $type,
            'coolify_uuid' => $uuid,
            'display_name' => $name,
            'status' => $status,
            'project_uuid' => 'mock-project',
            'environment_uuid' => 'mock-production',
            'project' => 'Personal',
            'environment' => 'production',
            'domains' => $domains,
            'repository' => 'github.com/youssef/'.$uuid,
            'branch' => 'main',
            'image' => null,
        ];
    }
}

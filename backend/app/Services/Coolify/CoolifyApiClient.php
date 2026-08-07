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
use App\Services\Operations\SecretRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CoolifyApiClient implements CoolifyClientInterface
{
    public function __construct(
        private readonly CoolifyResourceMapper $mapper,
        private readonly SecretRedactor $redactor,
    ) {}

    public function status(): array
    {
        $version = $this->get('/version', cacheKey: 'coolify:version');
        $health = $this->get('/health', authenticated: false, cacheKey: 'coolify:health');

        return [
            'enabled' => (bool) config('coolify.enabled'),
            'driver' => 'api',
            'connected' => true,
            'version' => is_string($version) ? $version : (string) ($version['version'] ?? 'unknown'),
            'health' => is_string($health) ? $health : 'ok',
            'last_successful_connection' => now()->toISOString(),
        ];
    }

    public function capabilities(): array
    {
        return app(CoolifyCapabilityService::class)->capabilities();
    }

    public function resources(?string $type = null): array
    {
        $groups = [
            'application' => '/applications',
            'service' => '/services',
            'database' => '/databases',
            'server' => '/servers',
            'project' => '/projects',
        ];

        $selected = $type ? [$type => $groups[$type] ?? null] : $groups;
        $resources = [];

        foreach ($selected as $resourceType => $endpoint) {
            if (! $endpoint) {
                continue;
            }

            foreach ($this->get($endpoint, cacheKey: 'coolify:resources:'.$resourceType) as $item) {
                if (is_array($item)) {
                    $resources[] = $this->mapper->normalize($resourceType, $item);
                }
            }
        }

        return $resources;
    }

    public function resource(string $type, string $uuid): ?array
    {
        $endpoint = match ($type) {
            'application' => '/applications/'.$uuid,
            'service' => '/services/'.$uuid,
            'database' => '/databases/'.$uuid,
            'server' => '/servers/'.$uuid,
            default => throw new CoolifyUnsupportedCapabilityException('This Coolify resource type is not supported.'),
        };

        $payload = $this->get($endpoint);

        return is_array($payload) ? $this->mapper->normalize($type, $payload) : null;
    }

    public function deploy(CoolifyResourceLink $link, Deployment $deployment): array
    {
        if ($link->resource_type->value !== 'application') {
            throw new CoolifyUnsupportedCapabilityException('Only Coolify applications can be deployed.');
        }

        return $this->post('/deploy', [], ['uuid' => $link->coolify_uuid]);
    }

    public function cancelDeployment(string $deploymentUuid): array
    {
        return $this->post('/deployments/'.$deploymentUuid.'/cancel');
    }

    public function deployments(?CoolifyResourceLink $link = null): array
    {
        if ($link) {
            return $this->get('/deployments/applications/'.$link->coolify_uuid, ['take' => 20]);
        }

        return $this->get('/deployments', cacheKey: 'coolify:deployments:running');
    }

    public function deployment(string $deploymentUuid): ?array
    {
        $payload = $this->get('/deployments/'.$deploymentUuid);

        return is_array($payload) ? $payload : null;
    }

    public function deploymentLogs(string $deploymentUuid): array
    {
        $deployment = $this->deployment($deploymentUuid);

        return [
            'deployment_uuid' => $deploymentUuid,
            'logs' => $this->redactor->redact((string) ($deployment['logs'] ?? $deployment['log'] ?? 'Deployment logs are not exposed by this Coolify endpoint.')),
            'complete' => in_array((string) ($deployment['status'] ?? ''), ['finished', 'failed', 'cancelled'], true),
        ];
    }

    public function resourceAction(CoolifyResourceLink $link, string $action): array
    {
        if ($link->resource_type->value !== 'application' || ! in_array($action, ['start', 'stop', 'restart'], true)) {
            throw new CoolifyUnsupportedCapabilityException('This resource action is unavailable through the installed Coolify API.');
        }

        return $this->post('/applications/'.$link->coolify_uuid.'/'.$action);
    }

    /** @param array<string, mixed> $query */
    private function get(string $endpoint, array $query = [], bool $authenticated = true, ?string $cacheKey = null): mixed
    {
        if ($cacheKey) {
            return Cache::remember($cacheKey, (int) config('coolify.cache_seconds'), fn (): mixed => $this->send('get', $endpoint, $query, [], $authenticated));
        }

        return $this->send('get', $endpoint, $query, [], $authenticated);
    }

    /** @param array<string, mixed> $body @param array<string, mixed> $query */
    private function post(string $endpoint, array $body = [], array $query = []): array
    {
        $payload = $this->send('post', $endpoint, $query, $body);

        return is_array($payload) ? $payload : ['message' => (string) $payload];
    }

    /** @param array<string, mixed> $query @param array<string, mixed> $body */
    private function send(string $method, string $endpoint, array $query = [], array $body = [], bool $authenticated = true): mixed
    {
        $this->assertConfigured($authenticated);

        try {
            $response = $this->request($authenticated)
                ->withHeaders(['X-Request-ID' => (string) request()?->attributes->get('request_id')])
                ->{$method}(ltrim($endpoint, '/'), $method === 'get' ? $query : $body + $query);

            if ($response->status() === 429) {
                throw new CoolifyRateLimitException(retryAfter: (int) $response->header('Retry-After'));
            }

            if ($response->status() === 401 || $response->status() === 403) {
                throw new CoolifyAuthenticationException('Coolify rejected the configured API token.');
            }

            if ($response->status() === 404) {
                throw new CoolifyResourceNotFoundException('Coolify resource was not found.');
            }

            $response->throw();
            $contentType = $response->header('Content-Type', '');

            return str_contains($contentType, 'json') ? $response->json() : $response->body();
        } catch (CoolifyAuthenticationException|CoolifyRateLimitException|CoolifyResourceNotFoundException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new CoolifyConnectionException('Coolify is currently unreachable.');
        } catch (RequestException $exception) {
            throw new CoolifyConnectionException($this->redactor->redact($exception->getMessage()));
        }
    }

    private function request(bool $authenticated): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->timeout((int) config('coolify.timeout_seconds'))
            ->connectTimeout((int) config('coolify.connect_timeout_seconds'))
            ->retry((int) config('coolify.max_retries'), 250, throw: false)
            ->acceptJson()
            ->withOptions(['verify' => (bool) config('coolify.verify_tls')]);

        return $authenticated ? $request->withToken((string) config('coolify.api_token')) : $request;
    }

    private function assertConfigured(bool $authenticated): void
    {
        if (! (bool) config('coolify.enabled')) {
            throw new CoolifyConnectionException('Coolify integration is disabled.');
        }

        if ($authenticated && blank(config('coolify.api_token'))) {
            throw new CoolifyAuthenticationException('Coolify API token is not configured.');
        }
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) config('coolify.internal_url'), '/');

        return str_ends_with($url, '/api/v1') ? $url : $url.'/api/v1';
    }
}

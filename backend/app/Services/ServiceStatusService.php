<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use Illuminate\Support\Carbon;

class ServiceStatusService
{
    /**
     * @var array<int, string>
     */
    private array $allowlist = ['nginx', 'php-fpm', 'mysql', 'cloudflared', 'docker', 'coolify'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(fn (string $name): array => $this->status($name), $this->allowlist);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $name): array
    {
        abort_unless(in_array($name, $this->allowlist, true), 404, 'Service is not allowlisted.');

        $mock = config('youpanel.service_status_driver') === 'mock' || app()->environment(['local', 'testing']);

        return [
            'name' => $name,
            'label' => $this->label($name),
            'status' => $mock ? $this->mockStatus($name)->value : ServiceStatus::Unavailable->value,
            'read_only' => true,
            'checked_at' => Carbon::now()->toISOString(),
        ];
    }

    private function label(string $name): string
    {
        return match ($name) {
            'php-fpm' => 'PHP-FPM',
            'mysql' => 'MySQL',
            'cloudflared' => 'Cloudflare Tunnel',
            'coolify' => 'Coolify',
            default => strtoupper(substr($name, 0, 1)).substr($name, 1),
        };
    }

    private function mockStatus(string $name): ServiceStatus
    {
        return match ($name) {
            'coolify' => ServiceStatus::Degraded,
            default => ServiceStatus::Running,
        };
    }
}

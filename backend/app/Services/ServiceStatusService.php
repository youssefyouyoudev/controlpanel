<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class ServiceStatusService
{
    /**
     * @var array<int, string>
     */
    private array $allowlist = ['nginx', 'php-fpm', 'mysql', 'docker', 'cloudflared', 'redis', 'pm2', 'supervisor', 'coolify'];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $definitions = [
        'nginx' => [
            'label' => 'Nginx',
            'units' => ['nginx.service'],
            'binaries' => ['nginx'],
            'version' => ['nginx', '-v'],
        ],
        'php-fpm' => [
            'label' => 'PHP-FPM',
            'units' => ['php-fpm.service', 'php8.4-fpm.service', 'php8.3-fpm.service', 'php8.2-fpm.service', 'php8.1-fpm.service', 'php8.0-fpm.service', 'php7.4-fpm.service'],
            'binaries' => ['php-fpm', 'php-fpm8.4', 'php-fpm8.3', 'php-fpm8.2', 'php-fpm8.1', 'php-fpm8.0', 'php-fpm7.4'],
            'version' => ['php-fpm', '-v'],
        ],
        'mysql' => [
            'label' => 'MySQL / MariaDB',
            'units' => ['mysql.service', 'mariadb.service'],
            'binaries' => ['mysql', 'mariadb'],
            'version' => ['mysql', '--version'],
        ],
        'docker' => [
            'label' => 'Docker',
            'units' => ['docker.service'],
            'binaries' => ['docker'],
            'version' => ['docker', '--version'],
        ],
        'cloudflared' => [
            'label' => 'Cloudflare Tunnel',
            'units' => ['cloudflared.service'],
            'binaries' => ['cloudflared'],
            'version' => ['cloudflared', '--version'],
        ],
        'redis' => [
            'label' => 'Redis',
            'units' => ['redis-server.service', 'redis.service'],
            'binaries' => ['redis-server', 'redis-cli'],
            'version' => ['redis-server', '--version'],
        ],
        'pm2' => [
            'label' => 'PM2',
            'units' => ['pm2.service', 'pm2-root.service'],
            'unit_patterns' => ['pm2*.service'],
            'binaries' => ['pm2'],
            'version' => ['pm2', '--version'],
        ],
        'supervisor' => [
            'label' => 'Supervisor',
            'units' => ['supervisor.service', 'supervisord.service'],
            'binaries' => ['supervisord', 'supervisorctl'],
            'version' => ['supervisord', '--version'],
        ],
        'coolify' => [
            'label' => 'Coolify',
            'units' => ['coolify.service'],
            'unit_patterns' => ['coolify*.service'],
            'binaries' => ['coolify'],
            'paths' => ['/data/coolify', '/var/lib/coolify'],
        ],
    ];

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

        $mock = config('youpanel.service_status_driver') === 'mock'
            || (config('youpanel.service_status_driver') === 'auto' && app()->environment(['local', 'testing']));

        if ($mock || PHP_OS_FAMILY !== 'Linux') {
            return [
                'name' => $name,
                'label' => $this->label($name),
                'installed' => $mock,
                'status' => $mock ? $this->mockStatus($name)->value : ServiceStatus::Unavailable->value,
                'version' => null,
                'uptime_seconds' => null,
                'unit' => null,
                'read_only' => true,
                'checked_at' => Carbon::now()->toISOString(),
            ];
        }

        return Cache::remember("service_status.{$name}", now()->addSeconds(15), function () use ($name): array {
            $definition = $this->definitions[$name];
            $unit = $this->firstSystemdUnit($definition);
            $systemd = $unit ? $this->systemdState($unit) : null;
            $installed = $systemd !== null
                || $this->anyBinaryExists($definition['binaries'] ?? [])
                || $this->anyPathExists($definition['paths'] ?? []);

            return [
                'name' => $name,
                'label' => $this->label($name),
                'installed' => $installed,
                'status' => $this->serviceStatus($systemd, $installed)->value,
                'version' => $installed ? $this->version($definition) : null,
                'uptime_seconds' => $systemd ? $this->uptimeSeconds($systemd) : null,
                'unit' => $unit,
                'read_only' => true,
                'checked_at' => Carbon::now()->toISOString(),
            ];
        });
    }

    private function label(string $name): string
    {
        return (string) ($this->definitions[$name]['label'] ?? strtoupper(substr($name, 0, 1)).substr($name, 1));
    }

    private function mockStatus(string $name): ServiceStatus
    {
        return match ($name) {
            'coolify' => ServiceStatus::Degraded,
            default => ServiceStatus::Running,
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function firstSystemdUnit(array $definition): ?string
    {
        if (! $this->binaryExists('systemctl')) {
            return null;
        }

        foreach ($definition['units'] ?? [] as $unit) {
            if ($this->systemdState((string) $unit) !== null) {
                return (string) $unit;
            }
        }

        foreach ($definition['unit_patterns'] ?? [] as $pattern) {
            $process = $this->run(['systemctl', 'list-units', '--type=service', '--all', '--no-legend', (string) $pattern]);
            if (! $process->isSuccessful()) {
                continue;
            }

            $line = trim(strtok($process->getOutput(), "\n") ?: '');
            $columns = preg_split('/\s+/', $line) ?: [];
            if (($columns[0] ?? '') !== '') {
                return $columns[0];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function systemdState(string $unit): ?array
    {
        $process = $this->run(['systemctl', 'show', '--no-page', $unit, '--property=LoadState,ActiveState,SubState,ActiveEnterTimestamp,ExecMainStartTimestamp']);
        if (! $process->isSuccessful()) {
            return null;
        }

        $state = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $state[$key] = $value;
        }

        return ($state['LoadState'] ?? null) === 'not-found' ? null : $state;
    }

    /**
     * @param  array<string, string>|null  $systemd
     */
    private function serviceStatus(?array $systemd, bool $installed): ServiceStatus
    {
        if (! $installed) {
            return ServiceStatus::Unavailable;
        }

        if ($systemd === null) {
            return ServiceStatus::Unknown;
        }

        return match ($systemd['ActiveState'] ?? 'unknown') {
            'active', 'activating', 'reloading' => ServiceStatus::Running,
            'failed' => ServiceStatus::Failed,
            'inactive', 'deactivating' => ServiceStatus::Stopped,
            default => ServiceStatus::Unknown,
        };
    }

    /**
     * @param  array<string, string>  $systemd
     */
    private function uptimeSeconds(array $systemd): ?int
    {
        $timestamp = $systemd['ActiveEnterTimestamp'] ?: ($systemd['ExecMainStartTimestamp'] ?? null);
        if (! is_string($timestamp) || trim($timestamp) === '') {
            return null;
        }

        $startedAt = strtotime($timestamp);

        return $startedAt === false ? null : max(0, now()->timestamp - $startedAt);
    }

    /**
     * @param  array<int, string>  $binaries
     */
    private function anyBinaryExists(array $binaries): bool
    {
        return collect($binaries)->contains(fn (string $binary): bool => $this->binaryExists($binary));
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function anyPathExists(array $paths): bool
    {
        return collect($paths)->contains(fn (string $path): bool => file_exists($path));
    }

    private function binaryExists(string $binary): bool
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR) && is_executable($binary)) {
            return true;
        }

        $paths = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));
        foreach (array_unique([...$paths, '/usr/local/sbin', '/usr/local/bin', '/usr/sbin', '/usr/bin', '/sbin', '/bin']) as $path) {
            if (is_executable(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function version(array $definition): ?string
    {
        $command = $definition['version'] ?? null;
        if (! is_array($command) || ! isset($command[0]) || ! $this->binaryExists((string) $command[0])) {
            return null;
        }

        $process = $this->run(array_map('strval', $command));
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return $output === '' ? null : substr(preg_replace('/\s+/', ' ', $output) ?: $output, 0, 160);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): Process
    {
        $process = new Process($command);
        $process->setTimeout(2);
        $process->run();

        return $process;
    }
}

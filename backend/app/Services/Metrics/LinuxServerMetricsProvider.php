<?php

namespace App\Services\Metrics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class LinuxServerMetricsProvider implements ServerMetricsProvider
{
    public function collect(): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return $this->unavailable('Linux metrics are unavailable on this development OS.');
        }

        return Cache::remember('server_metrics.local', now()->addSeconds(10), fn (): array => [
            'available' => true,
            'hostname' => gethostname() ?: null,
            'os_name' => $this->osName(),
            'kernel_version' => php_uname('r') ?: null,
            'uptime_seconds' => $this->uptime(),
            'cpu' => ['usage_percent' => $this->cpuUsage(), 'load_average' => $this->loadAverage()],
            'memory' => $this->memory(),
            'disk' => $this->disk(),
            'network' => $this->network(),
            'collected_at' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'hostname' => gethostname() ?: null,
            'os_name' => PHP_OS_FAMILY,
            'kernel_version' => php_uname('r') ?: null,
            'uptime_seconds' => null,
            'cpu' => ['usage_percent' => null, 'load_average' => []],
            'memory' => ['total_bytes' => null, 'used_bytes' => null, 'usage_percent' => null],
            'disk' => ['total_bytes' => null, 'used_bytes' => null, 'usage_percent' => null],
            'network' => ['rx_bytes' => null, 'tx_bytes' => null],
            'collected_at' => Carbon::now()->toISOString(),
        ];
    }

    private function osName(): ?string
    {
        $release = @parse_ini_file('/etc/os-release');

        return is_array($release) ? ($release['PRETTY_NAME'] ?? null) : null;
    }

    private function uptime(): ?float
    {
        $contents = @file_get_contents('/proc/uptime');

        return $contents ? (float) explode(' ', trim($contents))[0] : null;
    }

    /**
     * @return array<int, float>
     */
    private function loadAverage(): array
    {
        $contents = @file_get_contents('/proc/loadavg');

        if (! $contents) {
            return [];
        }

        return array_map('floatval', array_slice(explode(' ', trim($contents)), 0, 3));
    }

    private function cpuUsage(): ?float
    {
        $stat = @file('/proc/stat');
        $line = is_array($stat) ? ($stat[0] ?? null) : null;

        if (! $line || ! str_starts_with($line, 'cpu ')) {
            return null;
        }

        $values = array_map('intval', preg_split('/\s+/', trim($line)) ?: []);
        array_shift($values);

        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
        $total = array_sum($values);
        $previous = Cache::pull('server_metrics.cpu_sample');
        Cache::put('server_metrics.cpu_sample', ['idle' => $idle, 'total' => $total], now()->addMinute());

        if (! is_array($previous) || ($total - (int) $previous['total']) <= 0) {
            return null;
        }

        $idleDelta = $idle - (int) $previous['idle'];
        $totalDelta = $total - (int) $previous['total'];

        return round(max(0, min(100, (1 - ($idleDelta / $totalDelta)) * 100)), 1);
    }

    /**
     * @return array<string, int|float|null>
     */
    private function memory(): array
    {
        $lines = @file('/proc/meminfo');

        if (! is_array($lines)) {
            return ['total_bytes' => null, 'used_bytes' => null, 'usage_percent' => null];
        }

        $values = [];
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z_()]+):\s+(\d+)/', $line, $matches) === 1) {
                $values[$matches[1]] = (int) $matches[2] * 1024;
            }
        }

        $total = $values['MemTotal'] ?? null;
        $available = $values['MemAvailable'] ?? null;
        $used = $total !== null && $available !== null ? $total - $available : null;

        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'usage_percent' => $total && $used !== null ? round(($used / $total) * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function disk(): array
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());
        $used = $total !== false && $free !== false ? $total - $free : null;

        return [
            'total_bytes' => $total === false ? null : (int) $total,
            'used_bytes' => $used === null ? null : (int) $used,
            'usage_percent' => $total && $used !== null ? round(($used / $total) * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function network(): array
    {
        $lines = @file('/proc/net/dev');

        if (! is_array($lines)) {
            return ['rx_bytes' => null, 'tx_bytes' => null];
        }

        $rx = 0;
        $tx = 0;
        foreach (array_slice($lines, 2) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [, $data] = explode(':', $line, 2);
            $columns = preg_split('/\s+/', trim($data)) ?: [];
            $rx += (int) ($columns[0] ?? 0);
            $tx += (int) ($columns[8] ?? 0);
        }

        return ['rx_bytes' => $rx, 'tx_bytes' => $tx];
    }
}

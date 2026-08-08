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
            'architecture' => php_uname('m') ?: null,
            'uptime_seconds' => $this->uptime(),
            'cpu' => [
                'model' => $this->cpuModel(),
                'cores' => $this->cpuCores(),
                'usage_percent' => $this->cpuUsage(),
                'load_average' => $this->loadAverage(),
            ],
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
            'architecture' => php_uname('m') ?: null,
            'uptime_seconds' => null,
            'cpu' => ['model' => null, 'cores' => null, 'usage_percent' => null, 'load_average' => []],
            'memory' => [
                'total_bytes' => null,
                'used_bytes' => null,
                'available_bytes' => null,
                'usage_percent' => null,
                'swap_total_bytes' => null,
                'swap_used_bytes' => null,
                'swap_usage_percent' => null,
            ],
            'disk' => [
                'total_bytes' => null,
                'used_bytes' => null,
                'free_bytes' => null,
                'usage_percent' => null,
                'filesystems' => [],
            ],
            'network' => ['rx_bytes' => null, 'tx_bytes' => null, 'interfaces' => []],
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

    private function cpuModel(): ?string
    {
        $lines = @file('/proc/cpuinfo');

        if (! is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            if (preg_match('/^(model name|Hardware)\s*:\s*(.+)$/i', trim($line), $matches) === 1) {
                return trim($matches[2]);
            }
        }

        return null;
    }

    private function cpuCores(): ?int
    {
        $lines = @file('/proc/cpuinfo');

        if (! is_array($lines)) {
            return null;
        }

        $processors = 0;
        foreach ($lines as $line) {
            if (preg_match('/^processor\s*:/', trim($line)) === 1) {
                $processors++;
            }
        }

        return $processors > 0 ? $processors : null;
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
            return [
                'total_bytes' => null,
                'used_bytes' => null,
                'available_bytes' => null,
                'usage_percent' => null,
                'swap_total_bytes' => null,
                'swap_used_bytes' => null,
                'swap_usage_percent' => null,
            ];
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
        $swapTotal = $values['SwapTotal'] ?? null;
        $swapFree = $values['SwapFree'] ?? null;
        $swapUsed = $swapTotal !== null && $swapFree !== null ? $swapTotal - $swapFree : null;

        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'available_bytes' => $available,
            'usage_percent' => $total && $used !== null ? round(($used / $total) * 100, 1) : null,
            'swap_total_bytes' => $swapTotal,
            'swap_used_bytes' => $swapUsed,
            'swap_usage_percent' => $swapTotal && $swapUsed !== null ? round(($swapUsed / $swapTotal) * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function disk(): array
    {
        $filesystems = $this->filesystems();
        $root = collect($filesystems)->firstWhere('mount', '/') ?? $this->filesystemFor(base_path(), 'application', 'local');

        return [
            'total_bytes' => $root['total_bytes'] ?? null,
            'used_bytes' => $root['used_bytes'] ?? null,
            'free_bytes' => $root['free_bytes'] ?? null,
            'usage_percent' => $root['usage_percent'] ?? null,
            'filesystems' => $filesystems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function network(): array
    {
        $lines = @file('/proc/net/dev');

        if (! is_array($lines)) {
            return ['rx_bytes' => null, 'tx_bytes' => null, 'interfaces' => []];
        }

        $rx = 0;
        $tx = 0;
        $interfaces = [];
        foreach (array_slice($lines, 2) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $data] = explode(':', $line, 2);
            $name = trim($name);
            $columns = preg_split('/\s+/', trim($data)) ?: [];
            $interfaceRx = (int) ($columns[0] ?? 0);
            $interfaceTx = (int) ($columns[8] ?? 0);
            $isLoopback = $name === 'lo';

            $interfaces[] = [
                'name' => $name,
                'rx_bytes' => $interfaceRx,
                'tx_bytes' => $interfaceTx,
                'is_loopback' => $isLoopback,
            ];

            if (! $isLoopback) {
                $rx += $interfaceRx;
                $tx += $interfaceTx;
            }
        }

        return ['rx_bytes' => $rx, 'tx_bytes' => $tx, 'interfaces' => $interfaces];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filesystems(): array
    {
        $lines = @file('/proc/mounts');

        if (! is_array($lines)) {
            return [];
        }

        $ignoredTypes = ['autofs', 'binfmt_misc', 'bpf', 'cgroup', 'cgroup2', 'configfs', 'debugfs', 'devpts', 'devtmpfs', 'efivarfs', 'fusectl', 'hugetlbfs', 'mqueue', 'overlay', 'proc', 'pstore', 'securityfs', 'sysfs', 'tmpfs', 'tracefs'];
        $filesystems = [];
        $seen = [];

        foreach ($lines as $line) {
            $columns = preg_split('/\s+/', trim($line)) ?: [];
            if (count($columns) < 3) {
                continue;
            }

            [$device, $mount, $type] = array_slice($columns, 0, 3);
            $mount = str_replace('\040', ' ', $mount);

            if (isset($seen[$mount]) || in_array($type, $ignoredTypes, true) || str_starts_with($mount, '/snap/')) {
                continue;
            }

            $filesystem = $this->filesystemFor($mount, $device, $type);
            if ($filesystem === null) {
                continue;
            }

            $filesystems[] = $filesystem;
            $seen[$mount] = true;
        }

        usort($filesystems, fn (array $a, array $b): int => strcmp((string) $a['mount'], (string) $b['mount']));

        return $filesystems;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function filesystemFor(string $mount, string $device, string $type): ?array
    {
        $total = @disk_total_space($mount);
        $free = @disk_free_space($mount);

        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        $used = $total - $free;

        return [
            'mount' => $mount,
            'device' => $device,
            'type' => $type,
            'total_bytes' => (int) $total,
            'used_bytes' => (int) $used,
            'free_bytes' => (int) $free,
            'usage_percent' => round(($used / $total) * 100, 1),
        ];
    }
}

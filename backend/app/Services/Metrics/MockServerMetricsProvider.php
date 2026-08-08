<?php

namespace App\Services\Metrics;

use Illuminate\Support\Carbon;

class MockServerMetricsProvider implements ServerMetricsProvider
{
    public function collect(): array
    {
        return [
            'available' => true,
            'hostname' => 'youpanel-local',
            'os_name' => PHP_OS_FAMILY,
            'kernel_version' => php_uname('r'),
            'architecture' => php_uname('m'),
            'uptime_seconds' => 432000,
            'cpu' => [
                'model' => 'Mock 8-Core CPU',
                'cores' => 8,
                'usage_percent' => 18.6,
                'load_average' => [0.24, 0.31, 0.28],
            ],
            'memory' => [
                'total_bytes' => 16_000_000_000,
                'used_bytes' => 7_040_000_000,
                'available_bytes' => 8_960_000_000,
                'usage_percent' => 44.0,
                'swap_total_bytes' => 2_000_000_000,
                'swap_used_bytes' => 128_000_000,
                'swap_usage_percent' => 6.4,
            ],
            'disk' => [
                'total_bytes' => 256_000_000_000,
                'used_bytes' => 99_840_000_000,
                'free_bytes' => 156_160_000_000,
                'usage_percent' => 39.0,
                'filesystems' => [[
                    'mount' => '/',
                    'device' => '/dev/mock',
                    'type' => 'ext4',
                    'total_bytes' => 256_000_000_000,
                    'used_bytes' => 99_840_000_000,
                    'free_bytes' => 156_160_000_000,
                    'usage_percent' => 39.0,
                ]],
            ],
            'network' => [
                'rx_bytes' => 128_000_000,
                'tx_bytes' => 42_000_000,
                'interfaces' => [[
                    'name' => 'eth0',
                    'rx_bytes' => 128_000_000,
                    'tx_bytes' => 42_000_000,
                    'is_loopback' => false,
                ]],
            ],
            'collected_at' => Carbon::now()->toISOString(),
        ];
    }
}

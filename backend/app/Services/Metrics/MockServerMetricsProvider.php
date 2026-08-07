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
            'uptime_seconds' => 432000,
            'cpu' => ['usage_percent' => 18.6, 'load_average' => [0.24, 0.31, 0.28]],
            'memory' => ['total_bytes' => 16_000_000_000, 'used_bytes' => 7_040_000_000, 'usage_percent' => 44.0],
            'disk' => ['total_bytes' => 256_000_000_000, 'used_bytes' => 99_840_000_000, 'usage_percent' => 39.0],
            'network' => ['rx_bytes' => 128_000_000, 'tx_bytes' => 42_000_000],
            'collected_at' => Carbon::now()->toISOString(),
        ];
    }
}

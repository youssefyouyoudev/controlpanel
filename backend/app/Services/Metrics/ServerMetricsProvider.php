<?php

namespace App\Services\Metrics;

interface ServerMetricsProvider
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array;
}

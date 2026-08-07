<?php

namespace App\Jobs;

use App\Models\WebsiteHealthCheck;
use App\Services\Operations\HealthCheckService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunWebsiteHealthCheckJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $healthCheckId) {}

    public function handle(HealthCheckService $health): void
    {
        $health->run(WebsiteHealthCheck::query()->with('website')->findOrFail($this->healthCheckId));
    }
}

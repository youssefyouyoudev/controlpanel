<?php

namespace Database\Factories;

use App\Enums\HealthStatus;
use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use App\Models\WebsiteHealthCheckResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteHealthCheckResult> */
class WebsiteHealthCheckResultFactory extends Factory
{
    protected $model = WebsiteHealthCheckResult::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'website_health_check_id' => WebsiteHealthCheck::factory(),
            'status' => HealthStatus::Healthy,
            'http_status' => 200,
            'response_time_ms' => 120,
            'failure_reason' => null,
            'metadata' => null,
            'checked_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\HealthStatus;
use App\Models\Website;
use App\Models\WebsiteHealthCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteHealthCheck> */
class WebsiteHealthCheckFactory extends Factory
{
    protected $model = WebsiteHealthCheck::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'url' => 'https://example.com',
            'expected_status' => 200,
            'timeout_seconds' => 5,
            'allow_internal' => false,
            'status' => HealthStatus::Unknown,
            'consecutive_failures' => 0,
            'is_active' => true,
        ];
    }
}

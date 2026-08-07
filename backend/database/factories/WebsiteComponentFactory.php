<?php

namespace Database\Factories;

use App\Enums\HealthStatus;
use App\Enums\WebsiteComponentType;
use App\Models\Website;
use App\Models\WebsiteComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteComponent> */
class WebsiteComponentFactory extends Factory
{
    protected $model = WebsiteComponent::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'name' => 'Portfolio Backend',
            'slug' => fake()->unique()->slug(2),
            'type' => WebsiteComponentType::Laravel,
            'relative_working_directory' => '',
            'runtime' => 'php',
            'process_manager' => null,
            'process_name' => null,
            'status' => HealthStatus::Unknown,
            'configuration' => null,
            'is_active' => true,
        ];
    }
}

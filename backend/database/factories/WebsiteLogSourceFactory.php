<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteLogSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteLogSource> */
class WebsiteLogSourceFactory extends Factory
{
    protected $model = WebsiteLogSource::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'website_component_id' => null,
            'name' => 'Laravel application',
            'slug' => fake()->unique()->slug(2),
            'type' => 'laravel',
            'absolute_path' => storage_path('logs/laravel.log'),
            'download_enabled' => false,
            'is_sensitive' => false,
            'is_active' => true,
            'configuration' => null,
        ];
    }
}

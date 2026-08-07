<?php

namespace Database\Factories;

use App\Enums\WebsiteStatus;
use App\Models\Server;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'server_id' => Server::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'domain' => fake()->domainName(),
            'framework' => fake()->randomElement(['Laravel + Next.js', 'Laravel + Blade', 'Next.js']),
            'root_path' => '/var/www/'.Str::slug($name),
            'repository_url' => fake()->optional()->url(),
            'repository_branch' => 'main',
            'status' => WebsiteStatus::Healthy,
            'coolify_uuid' => null,
            'assigned_port' => fake()->optional()->numberBetween(3000, 9999),
            'metadata' => null,
        ];
    }
}

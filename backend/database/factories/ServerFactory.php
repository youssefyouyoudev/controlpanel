<?php

namespace Database\Factories;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'hostname' => fake()->domainName(),
            'description' => fake()->sentence(),
            'operating_system' => 'Ubuntu 24.04 LTS',
            'is_local' => false,
            'status' => ServerStatus::Healthy,
            'last_seen_at' => now(),
            'metadata' => null,
        ];
    }
}

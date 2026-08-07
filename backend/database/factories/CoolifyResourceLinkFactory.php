<?php

namespace Database\Factories;

use App\Enums\CoolifyResourceType;
use App\Models\CoolifyResourceLink;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CoolifyResourceLink> */
class CoolifyResourceLinkFactory extends Factory
{
    protected $model = CoolifyResourceLink::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'website_component_id' => null,
            'server_id' => null,
            'resource_type' => CoolifyResourceType::Application,
            'coolify_uuid' => (string) Str::uuid(),
            'coolify_project_uuid' => (string) Str::uuid(),
            'coolify_environment_uuid' => (string) Str::uuid(),
            'display_name' => fake()->company().' App',
            'is_primary' => true,
            'is_active' => true,
            'last_synced_at' => now(),
            'last_status' => 'running',
            'metadata' => ['domains' => [fake()->domainName()]],
        ];
    }
}

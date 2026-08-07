<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteActionAssignment;
use App\Models\WebsiteComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteActionAssignment> */
class WebsiteActionAssignmentFactory extends Factory
{
    protected $model = WebsiteActionAssignment::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'website_component_id' => null,
            'action_key' => 'laravel.clear_cache',
            'is_enabled' => true,
            'custom_label' => null,
            'configuration' => null,
        ];
    }

    public function forComponent(): self
    {
        return $this->state(fn (array $attributes): array => [
            'website_component_id' => WebsiteComponent::factory(),
        ]);
    }
}

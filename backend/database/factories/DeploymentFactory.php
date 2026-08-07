<?php

namespace Database\Factories;

use App\Enums\DeploymentProvider;
use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use App\Models\Deployment;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deployment> */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'provider' => DeploymentProvider::Coolify,
            'trigger' => DeploymentTrigger::Manual,
            'requested_by' => User::factory(),
            'status' => DeploymentStatus::Succeeded,
            'branch' => 'main',
            'commit_sha' => fake()->sha1(),
            'commit_message' => fake()->sentence(),
            'started_at' => now()->subMinutes(3),
            'finished_at' => now(),
            'duration_seconds' => 180,
            'metadata' => [],
        ];
    }
}

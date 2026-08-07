<?php

namespace Database\Factories;

use App\Models\DeploymentPolicy;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeploymentPolicy> */
class DeploymentPolicyFactory extends Factory
{
    protected $model = DeploymentPolicy::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'environment' => 'production',
            'requires_clean_git' => false,
            'requires_backup' => false,
            'requires_approval' => true,
            'allowed_branches' => ['main'],
            'protected_branches' => ['main'],
            'cooldown_seconds' => 60,
            'maximum_concurrent_deployments' => 1,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\DeploymentApprovalStatus;
use App\Models\Deployment;
use App\Models\DeploymentApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeploymentApproval> */
class DeploymentApprovalFactory extends Factory
{
    protected $model = DeploymentApproval::class;

    public function definition(): array
    {
        return [
            'deployment_id' => Deployment::factory(),
            'required_by_policy' => true,
            'requested_by' => User::factory(),
            'status' => DeploymentApprovalStatus::Pending,
            'expires_at' => now()->addHour(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\ActionExecutionStatus;
use App\Enums\ActionRiskLevel;
use App\Models\ActionExecution;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ActionExecution> */
class ActionExecutionFactory extends Factory
{
    protected $model = ActionExecution::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'website_id' => Website::factory(),
            'website_component_id' => null,
            'action_key' => 'laravel.clear_cache',
            'requested_by' => User::factory(),
            'status' => ActionExecutionStatus::Queued,
            'risk_level' => ActionRiskLevel::Low,
            'request_options' => null,
            'summary' => null,
            'metadata' => null,
        ];
    }
}

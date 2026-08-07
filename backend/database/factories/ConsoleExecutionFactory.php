<?php

namespace Database\Factories;

use App\Enums\ConsoleExecutionStatus;
use App\Models\ConsoleExecution;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConsoleExecution> */
class ConsoleExecutionFactory extends Factory
{
    protected $model = ConsoleExecution::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'requested_by' => User::factory(),
            'command_alias' => 'git.status',
            'status' => ConsoleExecutionStatus::Succeeded,
            'exit_code' => 0,
            'output_preview' => 'On branch main',
        ];
    }
}

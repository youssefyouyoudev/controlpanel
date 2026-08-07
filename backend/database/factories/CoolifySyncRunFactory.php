<?php

namespace Database\Factories;

use App\Enums\CoolifySyncStatus;
use App\Models\CoolifySyncRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CoolifySyncRun> */
class CoolifySyncRunFactory extends Factory
{
    protected $model = CoolifySyncRun::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'status' => CoolifySyncStatus::Succeeded,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'created_links' => 0,
            'updated_links' => 1,
            'unmatched_resources' => 0,
            'errors' => [],
        ];
    }
}

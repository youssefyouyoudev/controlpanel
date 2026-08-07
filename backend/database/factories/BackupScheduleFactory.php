<?php

namespace Database\Factories;

use App\Enums\BackupType;
use App\Models\BackupSchedule;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BackupSchedule> */
class BackupScheduleFactory extends Factory
{
    protected $model = BackupSchedule::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'name' => 'Daily files',
            'backup_type' => BackupType::Files,
            'cron_expression' => '0 2 * * *',
            'retention_count' => 7,
            'retention_days' => 30,
            'is_enabled' => true,
            'configuration' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Backup> */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'website_id' => Website::factory(),
            'website_component_id' => null,
            'type' => BackupType::Files,
            'status' => BackupStatus::Queued,
            'requested_by' => User::factory(),
            'storage_disk' => 'local',
            'storage_path' => null,
            'metadata' => null,
        ];
    }
}

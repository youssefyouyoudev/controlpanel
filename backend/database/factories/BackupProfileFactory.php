<?php

namespace Database\Factories;

use App\Models\BackupProfile;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/** @extends Factory<BackupProfile> */
class BackupProfileFactory extends Factory
{
    protected $model = BackupProfile::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'name' => 'Demo database',
            'type' => 'mysql',
            'encrypted_configuration' => Crypt::encryptString(json_encode(['database' => 'demo'], JSON_THROW_ON_ERROR)),
            'is_active' => true,
        ];
    }
}

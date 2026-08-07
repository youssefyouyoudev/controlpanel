<?php

namespace Database\Factories;

use App\Models\AllowedPath;
use App\Models\TrashEntry;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrashEntry>
 */
class TrashEntryFactory extends Factory
{
    protected $model = TrashEntry::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'allowed_path_id' => AllowedPath::factory(),
            'deleted_by' => User::factory(),
            'original_relative_path' => 'old.txt',
            'trash_storage_path' => 'trash/example/old.txt',
            'item_type' => 'file',
            'original_size' => 12,
            'checksum' => hash('sha256', 'old'),
            'expires_at' => now()->addDays(30),
            'metadata' => null,
            'created_at' => now(),
            'restored_at' => null,
        ];
    }
}

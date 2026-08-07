<?php

namespace Database\Factories;

use App\Models\AllowedPath;
use App\Models\FileRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileRevision>
 */
class FileRevisionFactory extends Factory
{
    protected $model = FileRevision::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'allowed_path_id' => AllowedPath::factory(),
            'user_id' => User::factory(),
            'relative_path' => 'README.md',
            'relative_path_hash' => hash('sha256', 'README.md'),
            'operation' => 'save',
            'original_size' => 12,
            'new_size' => 14,
            'original_checksum' => hash('sha256', 'before'),
            'new_checksum' => hash('sha256', 'after'),
            'storage_path' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}

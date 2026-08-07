<?php

namespace Database\Factories;

use App\Models\AllowedPath;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllowedPath>
 */
class AllowedPathFactory extends Factory
{
    protected $model = AllowedPath::class;

    public function definition(): array
    {
        $path = storage_path('app/testing-workspaces/'.fake()->uuid());

        return [
            'website_id' => Website::factory(),
            'name' => 'Project Root',
            'relative_label' => 'Project Root',
            'absolute_path' => $path,
            'absolute_path_hash' => hash('sha256', $path),
            'is_primary' => true,
            'can_read' => true,
            'can_write' => true,
            'can_upload' => true,
            'can_create' => true,
            'can_rename' => true,
            'can_move' => true,
            'can_copy' => true,
            'can_delete' => true,
            'can_archive' => true,
            'can_extract' => true,
            'max_upload_bytes' => null,
            'allowed_extensions' => null,
            'blocked_patterns' => null,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function readOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'can_write' => false,
            'can_upload' => false,
            'can_create' => false,
            'can_rename' => false,
            'can_move' => false,
            'can_delete' => false,
            'can_extract' => false,
        ]);
    }
}

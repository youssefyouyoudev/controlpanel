<?php

namespace App\Models;

use Database\Factories\AllowedPathFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllowedPath extends Model
{
    /** @use HasFactory<AllowedPathFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'absolute_path',
        'absolute_path_hash',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::saving(function (AllowedPath $allowedPath): void {
            $allowedPath->absolute_path = self::normalizeRootPath($allowedPath->absolute_path);
            $allowedPath->absolute_path_hash = hash('sha256', $allowedPath->absolute_path);
        });
    }

    public static function normalizeRootPath(string $path): string
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, trim($path));
        $real = realpath($path);

        return rtrim($real !== false ? $real : $path, DIRECTORY_SEPARATOR);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(FileRevision::class);
    }

    public function trashEntries(): HasMany
    {
        return $this->hasMany(TrashEntry::class);
    }

    public function operationEnabled(string $operation): bool
    {
        return match ($operation) {
            'read', 'list', 'metadata', 'search', 'download' => (bool) $this->can_read,
            'write', 'save' => (bool) $this->can_write,
            'upload' => (bool) $this->can_upload,
            'create', 'mkdir' => (bool) $this->can_create,
            'rename' => (bool) $this->can_rename,
            'move' => (bool) $this->can_move,
            'copy' => (bool) $this->can_copy,
            'delete', 'trash' => (bool) $this->can_delete,
            'archive' => (bool) $this->can_archive,
            'extract' => (bool) $this->can_extract,
            default => false,
        };
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'can_read' => 'boolean',
            'can_write' => 'boolean',
            'can_upload' => 'boolean',
            'can_create' => 'boolean',
            'can_rename' => 'boolean',
            'can_move' => 'boolean',
            'can_copy' => 'boolean',
            'can_delete' => 'boolean',
            'can_archive' => 'boolean',
            'can_extract' => 'boolean',
            'max_upload_bytes' => 'integer',
            'allowed_extensions' => 'array',
            'blocked_patterns' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}

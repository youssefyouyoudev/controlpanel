<?php

namespace App\Models;

use Database\Factories\FileRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileRevision extends Model
{
    /** @use HasFactory<FileRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function allowedPath(): BelongsTo
    {
        return $this->belongsTo(AllowedPath::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'original_size' => 'integer',
            'new_size' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

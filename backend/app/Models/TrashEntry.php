<?php

namespace App\Models;

use Database\Factories\TrashEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrashEntry extends Model
{
    /** @use HasFactory<TrashEntryFactory> */
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

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    protected function casts(): array
    {
        return [
            'original_size' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }
}

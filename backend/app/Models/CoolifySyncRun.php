<?php

namespace App\Models;

use App\Enums\CoolifySyncStatus;
use Database\Factories\CoolifySyncRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CoolifySyncRun extends Model
{
    /** @use HasFactory<CoolifySyncRunFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (CoolifySyncRun $run): void {
            $run->uuid ??= (string) Str::uuid();
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CoolifySyncStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'errors' => 'array',
            'metadata' => 'array',
        ];
    }
}

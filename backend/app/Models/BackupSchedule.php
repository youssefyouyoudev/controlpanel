<?php

namespace App\Models;

use App\Enums\BackupType;
use Database\Factories\BackupScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSchedule extends Model
{
    /** @use HasFactory<BackupScheduleFactory> */
    use HasFactory;

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    protected function casts(): array
    {
        return [
            'backup_type' => BackupType::class,
            'retention_count' => 'integer',
            'retention_days' => 'integer',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'configuration' => 'array',
        ];
    }
}

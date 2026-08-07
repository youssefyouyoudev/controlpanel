<?php

namespace App\Models;

use App\Enums\HealthStatus;
use Database\Factories\WebsiteHealthCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteHealthCheck extends Model
{
    /** @use HasFactory<WebsiteHealthCheckFactory> */
    use HasFactory;

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(WebsiteHealthCheckResult::class);
    }

    protected function casts(): array
    {
        return [
            'expected_status' => 'integer',
            'timeout_seconds' => 'integer',
            'allow_internal' => 'boolean',
            'status' => HealthStatus::class,
            'consecutive_failures' => 'integer',
            'last_checked_at' => 'datetime',
            'tls_metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

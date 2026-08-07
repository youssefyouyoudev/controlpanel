<?php

namespace App\Models;

use App\Enums\HealthStatus;
use Database\Factories\WebsiteHealthCheckResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteHealthCheckResult extends Model
{
    /** @use HasFactory<WebsiteHealthCheckResultFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function healthCheck(): BelongsTo
    {
        return $this->belongsTo(WebsiteHealthCheck::class, 'website_health_check_id');
    }

    protected function casts(): array
    {
        return [
            'status' => HealthStatus::class,
            'http_status' => 'integer',
            'response_time_ms' => 'integer',
            'metadata' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\ActionExecutionStatus;
use App\Enums\ActionRiskLevel;
use Database\Factories\ActionExecutionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActionExecution extends Model
{
    /** @use HasFactory<ActionExecutionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (ActionExecution $execution): void {
            $execution->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(WebsiteComponent::class, 'website_component_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('website', fn (Builder $websites): Builder => $websites->visibleTo($user));
    }

    protected function casts(): array
    {
        return [
            'status' => ActionExecutionStatus::class,
            'risk_level' => ActionRiskLevel::class,
            'request_options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

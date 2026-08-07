<?php

namespace App\Models;

use App\Enums\ConsoleExecutionStatus;
use Database\Factories\ConsoleExecutionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ConsoleExecution extends Model
{
    /** @use HasFactory<ConsoleExecutionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (ConsoleExecution $execution): void {
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
            'status' => ConsoleExecutionStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

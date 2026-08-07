<?php

namespace App\Models;

use App\Enums\DeploymentProvider;
use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Deployment $deployment): void {
            $deployment->uuid ??= (string) Str::uuid();
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

    public function resourceLink(): BelongsTo
    {
        return $this->belongsTo(CoolifyResourceLink::class, 'coolify_resource_link_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approval(): HasOne
    {
        return $this->hasOne(DeploymentApproval::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('website', fn (Builder $websites): Builder => $websites->visibleTo($user));
    }

    protected function casts(): array
    {
        return [
            'provider' => DeploymentProvider::class,
            'trigger' => DeploymentTrigger::class,
            'status' => DeploymentStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'preflight' => 'array',
            'metadata' => 'array',
        ];
    }
}

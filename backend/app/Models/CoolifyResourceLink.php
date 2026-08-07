<?php

namespace App\Models;

use App\Enums\CoolifyResourceType;
use Database\Factories\CoolifyResourceLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoolifyResourceLink extends Model
{
    /** @use HasFactory<CoolifyResourceLinkFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'metadata',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(WebsiteComponent::class, 'website_component_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('website', fn (Builder $websites): Builder => $websites->visibleTo($user));
    }

    protected function casts(): array
    {
        return [
            'resource_type' => CoolifyResourceType::class,
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

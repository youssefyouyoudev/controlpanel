<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        $visibleWebsiteIds = Website::query()->visibleTo($user)->select('id');

        return $query->where(function (Builder $query) use ($user, $visibleWebsiteIds): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('website_id', $visibleWebsiteIds);
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

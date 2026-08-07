<?php

namespace App\Models;

use App\Enums\WebsiteMemberRole;
use Database\Factories\WebsiteMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteMember extends Model
{
    /** @use HasFactory<WebsiteMemberFactory> */
    use HasFactory;

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'role' => WebsiteMemberRole::class,
        ];
    }
}

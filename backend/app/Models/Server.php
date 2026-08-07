<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected $guarded = [];

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    protected function casts(): array
    {
        return [
            'is_local' => 'boolean',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
            'status' => ServerStatus::class,
        ];
    }
}

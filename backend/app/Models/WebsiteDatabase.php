<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteDatabase extends Model
{
    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'port' => 'integer',
        ];
    }
}

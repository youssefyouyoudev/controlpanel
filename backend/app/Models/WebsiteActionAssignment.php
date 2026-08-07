<?php

namespace App\Models;

use Database\Factories\WebsiteActionAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteActionAssignment extends Model
{
    /** @use HasFactory<WebsiteActionAssignmentFactory> */
    use HasFactory;

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(WebsiteComponent::class, 'website_component_id');
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'configuration' => 'array',
        ];
    }
}

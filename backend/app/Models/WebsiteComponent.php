<?php

namespace App\Models;

use App\Enums\HealthStatus;
use App\Enums\WebsiteComponentType;
use Database\Factories\WebsiteComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteComponent extends Model
{
    /** @use HasFactory<WebsiteComponentFactory> */
    use HasFactory;

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function actionAssignments(): HasMany
    {
        return $this->hasMany(WebsiteActionAssignment::class);
    }

    public function actionExecutions(): HasMany
    {
        return $this->hasMany(ActionExecution::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    protected function casts(): array
    {
        return [
            'type' => WebsiteComponentType::class,
            'status' => HealthStatus::class,
            'configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

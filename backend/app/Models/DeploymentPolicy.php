<?php

namespace App\Models;

use Database\Factories\DeploymentPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentPolicy extends Model
{
    /** @use HasFactory<DeploymentPolicyFactory> */
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
            'requires_clean_git' => 'boolean',
            'requires_backup' => 'boolean',
            'requires_approval' => 'boolean',
            'allowed_branches' => 'array',
            'protected_branches' => 'array',
            'maintenance_mode' => 'boolean',
            'health_check_after_deploy' => 'boolean',
            'auto_rollback_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }
}

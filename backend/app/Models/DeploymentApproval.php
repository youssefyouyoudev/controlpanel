<?php

namespace App\Models;

use App\Enums\DeploymentApprovalStatus;
use Database\Factories\DeploymentApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentApproval extends Model
{
    /** @use HasFactory<DeploymentApprovalFactory> */
    use HasFactory;

    protected $guarded = [];

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'required_by_policy' => 'boolean',
            'status' => DeploymentApprovalStatus::class,
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'approval_fingerprint' => 'array',
        ];
    }
}

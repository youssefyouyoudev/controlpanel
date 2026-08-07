<?php

namespace App\Models;

use App\Enums\WebsiteStatus;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'root_path',
        'metadata',
        'coolify_uuid',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'website_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function allowedPaths(): HasMany
    {
        return $this->hasMany(AllowedPath::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(WebsiteComponent::class);
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

    public function backupSchedules(): HasMany
    {
        return $this->hasMany(BackupSchedule::class);
    }

    public function logSources(): HasMany
    {
        return $this->hasMany(WebsiteLogSource::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(WebsiteHealthCheck::class);
    }

    public function healthCheckResults(): HasMany
    {
        return $this->hasMany(WebsiteHealthCheckResult::class);
    }

    public function coolifyResourceLinks(): HasMany
    {
        return $this->hasMany(CoolifyResourceLink::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function deploymentPolicies(): HasMany
    {
        return $this->hasMany(DeploymentPolicy::class);
    }

    public function consoleExecutions(): HasMany
    {
        return $this->hasMany(ConsoleExecution::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereHas('members', fn (Builder $members): Builder => $members->whereKey($user->id));
    }

    public function safeDisplayPathFor(User $user): ?string
    {
        return $user->isOwner() ? $this->root_path : null;
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'status' => WebsiteStatus::class,
            'assigned_port' => 'integer',
        ];
    }
}

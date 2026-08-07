<?php

namespace App\Jobs;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecoverStaleDeploymentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Deployment::query()
            ->whereIn('status', [DeploymentStatus::Queued, DeploymentStatus::Preparing, DeploymentStatus::Building, DeploymentStatus::Deploying, DeploymentStatus::Running])
            ->where('updated_at', '<', now()->subMinutes((int) config('coolify.deployment_timeout_minutes')))
            ->update(['status' => DeploymentStatus::TimedOut, 'finished_at' => now(), 'failure_reason' => 'Deployment timed out while waiting for Coolify.']);
    }
}

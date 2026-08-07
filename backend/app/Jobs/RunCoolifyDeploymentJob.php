<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\Coolify\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunCoolifyDeploymentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $deploymentId) {}

    public function handle(DeploymentService $deployments): void
    {
        $deployments->run(Deployment::query()->with(['website.members', 'component', 'resourceLink', 'requester'])->findOrFail($this->deploymentId));
    }
}

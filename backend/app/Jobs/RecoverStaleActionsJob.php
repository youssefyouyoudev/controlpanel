<?php

namespace App\Jobs;

use App\Enums\ActionExecutionStatus;
use App\Models\ActionExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecoverStaleActionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        ActionExecution::query()
            ->whereIn('status', [ActionExecutionStatus::Queued, ActionExecutionStatus::Preparing, ActionExecutionStatus::Running])
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update([
                'status' => ActionExecutionStatus::TimedOut,
                'finished_at' => now(),
                'failure_reason' => 'The action was marked timed out by stale action recovery.',
            ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\ActionExecution;
use App\Services\Operations\ActionExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteActionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $executionId) {}

    public function handle(ActionExecutionService $service): void
    {
        $execution = ActionExecution::query()->with(['website', 'component', 'requester'])->findOrFail($this->executionId);
        $service->run($execution);
    }
}

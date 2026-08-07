<?php

namespace App\Jobs;

use App\Models\ConsoleExecution;
use App\Services\Console\RestrictedConsoleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteConsoleCommandJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $executionId) {}

    public function handle(RestrictedConsoleService $console): void
    {
        $console->run(ConsoleExecution::query()->with(['website', 'component', 'requester'])->findOrFail($this->executionId));
    }
}

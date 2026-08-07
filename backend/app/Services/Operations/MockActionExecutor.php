<?php

namespace App\Services\Operations;

use App\Data\ActionRunResultData;
use App\Models\ActionExecution;

class MockActionExecutor implements ActionExecutorInterface
{
    public function run(ActionExecution $execution, array $definition, string $workingDirectory): ActionRunResultData
    {
        $key = $execution->action_key;
        $output = match ($key) {
            'git.pull_fast_forward' => "Mock git pull blocked: local changes would be overwritten.\n",
            'npm.build' => "Mock npm build completed.\nCompiled successfully.\n",
            'laravel.migrate' => "Mock migration status checked.\nNo production migration was run.\n",
            default => "Mock action {$key} executed in {$workingDirectory}.\nNo production command was run.\n",
        };

        $failed = $key === 'demo.failed_build';

        return new ActionRunResultData(
            exitCode: $failed ? 1 : 0,
            output: $output,
            summary: $failed ? 'The simulated action failed.' : "The {$definition['name']} action completed in mock mode.",
        );
    }
}

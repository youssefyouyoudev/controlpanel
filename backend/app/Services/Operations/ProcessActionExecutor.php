<?php

namespace App\Services\Operations;

use App\Data\ActionRunResultData;
use App\Exceptions\OperationBlockedException;
use App\Models\ActionExecution;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ProcessActionExecutor implements ActionExecutorInterface
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    public function run(ActionExecution $execution, array $definition, string $workingDirectory): ActionRunResultData
    {
        $command = $this->commandFor($definition, $execution);
        $this->assertPreflight($definition, $workingDirectory);

        $process = new Process($command, $workingDirectory, $this->safeEnvironment(), null, (int) ($definition['timeout_seconds'] ?? config('youpanel.operations.default_timeout_seconds')));
        $process->setIdleTimeout(min(60, max(10, (int) ($definition['timeout_seconds'] ?? 120))));

        $output = '';
        $maxBytes = (int) config('youpanel.operations.output_max_bytes');

        try {
            $process->run(function (string $type, string $buffer) use (&$output, $maxBytes): void {
                if (strlen($output) < $maxBytes) {
                    $output .= substr($buffer, 0, max(0, $maxBytes - strlen($output)));
                }
            });
        } catch (ProcessTimedOutException) {
            $process->stop(3);

            return new ActionRunResultData(124, $this->redactor->redact($output), 'The action timed out before completing.', true);
        }

        $output = $this->redactor->redact($output.$process->getErrorOutput());

        return new ActionRunResultData(
            exitCode: $process->getExitCode() ?? 1,
            output: $output,
            summary: $process->isSuccessful() ? 'The action completed successfully.' : 'The action failed. Review the captured output for details.',
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function commandFor(array $definition, ActionExecution $execution): array
    {
        $command = $definition['command'] ?? null;
        if (! is_array($command) || $command === []) {
            throw new OperationBlockedException('This action does not have a process command.');
        }

        return array_map(function (mixed $part) use ($execution): string {
            $value = (string) $part;
            if ($value === '__PROCESS_NAME__') {
                $process = $execution->component?->process_name;
                if (! $process) {
                    throw new OperationBlockedException('No PM2 process is configured for this component.');
                }

                return $process;
            }

            return $value;
        }, $command);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertPreflight(array $definition, string $workingDirectory): void
    {
        $executor = $definition['executor'] ?? '';

        if ($executor === 'laravel' && ! is_file($workingDirectory.DIRECTORY_SEPARATOR.'artisan')) {
            throw new OperationBlockedException('This component does not look like a Laravel application.');
        }

        if ($executor === 'composer' && ! is_file($workingDirectory.DIRECTORY_SEPARATOR.'composer.json')) {
            throw new OperationBlockedException('composer.json was not found.');
        }

        if ($executor === 'npm' && ! is_file($workingDirectory.DIRECTORY_SEPARATOR.'package.json')) {
            throw new OperationBlockedException('package.json was not found.');
        }

        if ($executor === 'npm' && in_array(($definition['key'] ?? ''), ['npm.install_ci'], true) && ! is_file($workingDirectory.DIRECTORY_SEPARATOR.'package-lock.json')) {
            throw new OperationBlockedException('package-lock.json is required for npm ci.');
        }

        if ($executor === 'git' && ! is_dir($workingDirectory.DIRECTORY_SEPARATOR.'.git')) {
            throw new OperationBlockedException('This working directory is not a Git repository.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function safeEnvironment(): array
    {
        $env = [];
        foreach (config('youpanel.operations.safe_environment', []) as $key) {
            $value = getenv((string) $key);
            if (is_string($value)) {
                $env[(string) $key] = $value;
            }
        }

        return $env;
    }
}

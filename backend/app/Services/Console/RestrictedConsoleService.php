<?php

namespace App\Services\Console;

use App\Enums\ConsoleExecutionStatus;
use App\Exceptions\OperationBlockedException;
use App\Jobs\ExecuteConsoleCommandJob;
use App\Models\ConsoleExecution;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\AuditLogger;
use App\Services\Operations\OperationWorkspaceResolver;
use App\Services\Operations\SecretRedactor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RestrictedConsoleService
{
    public function __construct(
        private readonly OperationWorkspaceResolver $workspaceResolver,
        private readonly SecretRedactor $redactor,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function commands(?WebsiteComponent $component = null): array
    {
        return collect(config('youpanel-console.commands', []))
            ->map(fn (array $definition, string $alias): array => [
                'alias' => $alias,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'available' => $this->appliesTo($definition, $component),
            ])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function request(Website $website, User $user, array $data): ConsoleExecution
    {
        if (! $user->can('view', $website) || ! in_array($user->role->value, ['owner', 'developer'], true)) {
            throw new OperationBlockedException('Your role cannot use the restricted project console.');
        }

        $this->assertNoRawCommand($data);
        $alias = (string) $data['command_alias'];
        $definition = $this->definition($alias);
        $component = isset($data['website_component_id'])
            ? WebsiteComponent::query()->whereBelongsTo($website)->findOrFail($data['website_component_id'])
            : $website->components()->first();

        if (! $this->appliesTo($definition, $component)) {
            throw new OperationBlockedException('This command is not available for the selected component.');
        }

        $this->workspaceResolver->resolve($website, $user, $component);

        $execution = ConsoleExecution::query()->create([
            'website_id' => $website->id,
            'website_component_id' => $component?->id,
            'requested_by' => $user->id,
            'command_alias' => $alias,
            'status' => ConsoleExecutionStatus::Queued,
            'metadata' => ['label' => $definition['label']],
        ]);

        $this->auditLogger->record('console.requested', $user, $website, ['command_alias' => $alias, 'console_execution_uuid' => $execution->uuid]);
        ExecuteConsoleCommandJob::dispatch($execution->id);

        return $execution;
    }

    public function run(ConsoleExecution $execution): void
    {
        $lock = Cache::lock('console:user:'.$execution->requested_by, (int) config('coolify.console_command_timeout_seconds') + 30);

        if (! $lock->get()) {
            $execution->update(['status' => ConsoleExecutionStatus::Rejected, 'failure_reason' => 'Another console command is already running for this user.', 'finished_at' => now()]);

            return;
        }

        try {
            $definition = $this->definition($execution->command_alias);
            $workingDirectory = $this->workspaceResolver->resolve($execution->website, $execution->requester, $execution->component);
            $execution->update(['status' => ConsoleExecutionStatus::Running, 'started_at' => now()]);
            $output = $this->execute($definition, $workingDirectory);
            $path = $this->storeOutput($execution, $output['output']);
            $status = $output['exit_code'] === 0 ? ConsoleExecutionStatus::Succeeded : ConsoleExecutionStatus::Failed;
            $execution->update([
                'status' => $status,
                'finished_at' => now(),
                'exit_code' => $output['exit_code'],
                'output_storage_path' => $path,
                'output_preview' => substr($output['output'], 0, (int) config('coolify.console_output_max_bytes')),
                'failure_reason' => $status === ConsoleExecutionStatus::Failed ? 'The command exited with a non-zero status.' : null,
            ]);
            $this->auditLogger->record('console.completed', $execution->requester, $execution->website, ['command_alias' => $execution->command_alias, 'console_execution_uuid' => $execution->uuid]);
        } catch (\Throwable $exception) {
            $execution->update(['status' => ConsoleExecutionStatus::Failed, 'finished_at' => now(), 'failure_reason' => $this->redactor->redact($exception->getMessage())]);
        } finally {
            $lock->release();
        }
    }

    public function output(ConsoleExecution $execution): string
    {
        if (! $execution->output_storage_path) {
            return $execution->output_preview ?? '';
        }

        return File::exists(storage_path('app/private/'.$execution->output_storage_path))
            ? File::get(storage_path('app/private/'.$execution->output_storage_path))
            : ($execution->output_preview ?? '');
    }

    /** @return array<string, mixed> */
    private function definition(string $alias): array
    {
        $commands = config('youpanel-console.commands', []);
        $definition = is_array($commands) ? ($commands[$alias] ?? null) : null;

        if (! is_array($definition)) {
            throw new OperationBlockedException('Unknown restricted console command.');
        }

        return $definition;
    }

    /** @param array<string, mixed> $definition */
    private function appliesTo(array $definition, ?WebsiteComponent $component): bool
    {
        $types = $definition['component_types'] ?? [];

        return $types === [] || ($component && in_array($component->type->value, $types, true));
    }

    /** @param array<string, mixed> $data */
    private function assertNoRawCommand(array $data): void
    {
        foreach (['command', 'cmd', 'args', 'executable', 'container_id', 'coolify_uuid'] as $field) {
            if (array_key_exists($field, $data)) {
                throw new OperationBlockedException('The restricted console accepts command aliases only.');
            }
        }
    }

    /** @param array<string, mixed> $definition @return array{output: string, exit_code: int} */
    private function execute(array $definition, string $workingDirectory): array
    {
        if ((bool) config('youpanel-console.mock')) {
            return ['output' => $this->redactor->redact('Restricted project console mock output for '.$definition['label']."\nTOKEN=secret"), 'exit_code' => 0];
        }

        $process = new Process($definition['command'], $workingDirectory, null, null, (int) config('coolify.console_command_timeout_seconds'));
        $process->run();

        return [
            'output' => $this->redactor->redact(substr($process->getOutput().$process->getErrorOutput(), 0, (int) config('coolify.console_output_max_bytes'))),
            'exit_code' => $process->getExitCode() ?? 1,
        ];
    }

    private function storeOutput(ConsoleExecution $execution, string $output): string
    {
        $path = 'console-output/'.$execution->website_id.'/'.$execution->uuid.'.log';
        $absolute = storage_path('app/private/'.$path);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $output);

        return $path;
    }
}

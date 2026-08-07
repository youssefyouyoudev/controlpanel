<?php

namespace App\Services\Operations;

use App\Enums\ActionExecutionStatus;
use App\Enums\ActionRiskLevel;
use App\Exceptions\OperationBlockedException;
use App\Jobs\ExecuteActionJob;
use App\Models\ActionExecution;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Notifications\YouPanelNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class ActionExecutionService
{
    public function __construct(
        private readonly ActionCatalog $catalog,
        private readonly OperationWorkspaceResolver $workspaceResolver,
        private readonly SecretRedactor $redactor,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function request(Website $website, User $user, string $actionKey, ?WebsiteComponent $component, array $options = []): ActionExecution
    {
        if (! $user->can('view', $website)) {
            throw new OperationBlockedException('You cannot access this website.');
        }

        if ($component && $component->website_id !== $website->id) {
            throw new OperationBlockedException('The selected component does not belong to this website.');
        }

        $definition = $this->catalog->get($actionKey);
        $this->catalog->assertCanRun($user, $definition, $component);
        $this->assertAssignmentEnabled($website, $component, $actionKey);
        $this->assertConfirmation($website, $user, $definition, $options);
        $this->workspaceResolver->resolve($website, $user, $component);

        $execution = ActionExecution::query()->create([
            'website_id' => $website->id,
            'website_component_id' => $component?->id,
            'action_key' => $actionKey,
            'requested_by' => $user->id,
            'status' => ActionExecutionStatus::Queued,
            'risk_level' => ActionRiskLevel::from((string) $definition['risk_level']),
            'request_options' => $this->redactor->scrubArray($options),
            'request_id' => request()?->attributes->get('request_id'),
            'metadata' => ['definition_name' => $definition['name']],
        ]);

        $this->auditLogger->record('action.requested', $user, $website, ['target_type' => 'action', 'target_identifier' => $actionKey, 'execution_uuid' => $execution->uuid]);
        ExecuteActionJob::dispatch($execution->id);

        return $execution;
    }

    public function run(ActionExecution $execution): void
    {
        $definition = $this->catalog->get($execution->action_key);
        $lock = Cache::lock('action:website:'.$execution->website_id, (int) ($definition['timeout_seconds'] ?? 120) + 60);
        $lockAcquired = false;

        if (! ($definition['concurrent'] ?? false)) {
            $lockAcquired = $lock->get();

            if (! $lockAcquired) {
                $execution->update(['status' => ActionExecutionStatus::Blocked, 'failure_reason' => 'Another conflicting action is already running for this website.', 'finished_at' => now()]);

                return;
            }
        }

        try {
            $execution->update(['status' => ActionExecutionStatus::Preparing]);
            $workingDirectory = $this->workspaceResolver->resolve($execution->website, $execution->requester, $execution->component);
            $executor = $this->executorFor($definition);

            $execution->update(['status' => ActionExecutionStatus::Running, 'started_at' => now()]);
            $result = $executor->run($execution->load('component'), $definition, $workingDirectory);
            $outputPath = $this->storeOutput($execution, $result->output);
            $status = $result->timedOut ? ActionExecutionStatus::TimedOut : ($result->exitCode === 0 ? ActionExecutionStatus::Succeeded : ActionExecutionStatus::Failed);

            $execution->update([
                'status' => $status,
                'finished_at' => now(),
                'exit_code' => $result->exitCode,
                'summary' => $result->summary,
                'output_storage_path' => $outputPath,
                'output_preview' => $this->preview($result->output),
                'failure_reason' => $status === ActionExecutionStatus::Succeeded ? null : $result->summary,
            ]);

            $this->auditLogger->record($status === ActionExecutionStatus::Succeeded ? 'action.completed' : 'action.failed', $execution->requester, $execution->website, ['target_type' => 'action', 'target_identifier' => $execution->action_key, 'execution_uuid' => $execution->uuid]);
            $this->notify($execution, $status === ActionExecutionStatus::Succeeded ? 'Action completed' : 'Action failed', $result->summary, $status === ActionExecutionStatus::Succeeded ? 'success' : 'danger');
        } catch (\Throwable $exception) {
            $execution->update(['status' => ActionExecutionStatus::Failed, 'finished_at' => now(), 'failure_reason' => $exception->getMessage(), 'summary' => 'The action could not complete safely.']);
            $this->auditLogger->record('action.failed', $execution->requester, $execution->website, ['target_type' => 'action', 'target_identifier' => $execution->action_key, 'execution_uuid' => $execution->uuid, 'reason' => $exception->getMessage()]);
            $this->notify($execution, 'Action failed', $exception->getMessage(), 'danger');
        } finally {
            if ($lockAcquired) {
                $lock->release();
            }
        }
    }

    public function cancel(ActionExecution $execution, User $user): ActionExecution
    {
        $this->catalog->assertCanRun($user, $this->catalog->get($execution->action_key), $execution->component);

        if (! in_array($execution->status, [ActionExecutionStatus::Queued, ActionExecutionStatus::Preparing], true)) {
            throw new OperationBlockedException('Only queued or preparing actions can be cancelled safely in Phase 3.');
        }

        $execution->update(['status' => ActionExecutionStatus::Cancelled, 'finished_at' => now(), 'summary' => 'The action was cancelled before execution.']);
        $this->auditLogger->record('action.cancelled', $user, $execution->website, ['target_type' => 'action', 'target_identifier' => $execution->action_key, 'execution_uuid' => $execution->uuid]);

        return $execution->refresh();
    }

    private function executorFor(array $definition): ActionExecutorInterface
    {
        if ((bool) config('youpanel-actions.mock')) {
            return app(MockActionExecutor::class);
        }

        return app(ProcessActionExecutor::class);
    }

    private function assertAssignmentEnabled(Website $website, ?WebsiteComponent $component, string $actionKey): void
    {
        $assignment = $website->actionAssignments()
            ->where('action_key', $actionKey)
            ->where(function ($query) use ($component): void {
                $component ? $query->where('website_component_id', $component->id) : $query->whereNull('website_component_id');
            })
            ->first();

        if ($assignment && ! $assignment->is_enabled) {
            throw new OperationBlockedException('This action is disabled for the selected website or component.');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $options
     */
    private function assertConfirmation(Website $website, User $user, array $definition, array $options): void
    {
        if (($definition['requires_confirmation'] ?? false) && ($options['confirmed'] ?? false) !== true) {
            throw new OperationBlockedException('This action requires explicit confirmation.');
        }

        if (($definition['requires_password_confirmation'] ?? false)) {
            if (($options['typed_website_name'] ?? null) !== $website->name) {
                throw new OperationBlockedException('Type the website name exactly before running this high-risk action.');
            }

            if (! Hash::check((string) ($options['password'] ?? ''), (string) $user->password)) {
                throw new OperationBlockedException('Password confirmation failed.');
            }
        }
    }

    private function storeOutput(ActionExecution $execution, string $output): string
    {
        $path = 'action-output/'.$execution->website_id.'/'.$execution->uuid.'.log';
        $absolute = storage_path('app/private/'.$path);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $output);

        return $path;
    }

    private function preview(string $output): string
    {
        return substr($output, 0, (int) config('youpanel.operations.output_preview_bytes'));
    }

    private function notify(ActionExecution $execution, string $title, string $body, string $severity): void
    {
        $users = $execution->website->members()->get()->push($execution->requester)->unique('id');
        Notification::send($users, new YouPanelNotification($title, $body, $severity, '/actions/'.$execution->uuid, ['execution_uuid' => $execution->uuid]));
    }
}

<?php

namespace App\Services\Coolify;

use App\Contracts\CoolifyClientInterface;
use App\Enums\DeploymentApprovalStatus;
use App\Enums\DeploymentProvider;
use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use App\Exceptions\OperationBlockedException;
use App\Jobs\RunCoolifyDeploymentJob;
use App\Models\Backup;
use App\Models\CoolifyResourceLink;
use App\Models\Deployment;
use App\Models\DeploymentApproval;
use App\Models\DeploymentPolicy;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Notifications\YouPanelNotification;
use App\Services\AuditLogger;
use App\Services\Operations\SecretRedactor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class DeploymentService
{
    public function __construct(
        private readonly CoolifyClientInterface $client,
        private readonly CoolifyCapabilityService $capabilities,
        private readonly AuditLogger $auditLogger,
        private readonly SecretRedactor $redactor,
    ) {}

    /** @param array<string, mixed> $data */
    public function request(Website $website, User $user, array $data): Deployment
    {
        if (! $user->can('view', $website) || ! in_array($user->role->value, ['owner', 'developer'], true)) {
            throw new OperationBlockedException('Your role cannot request deployments for this website.');
        }

        $link = CoolifyResourceLink::query()
            ->whereBelongsTo($website)
            ->whereKey($data['resource_link_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $component = $link->component;
        $policy = $this->policyFor($website, $component);
        $branch = (string) ($data['branch'] ?? $component?->repository_branch ?? $website->repository_branch ?? 'main');
        $preflight = $this->preflight($website, $policy, $branch);

        if (($data['confirmed'] ?? false) !== true) {
            throw new OperationBlockedException('Deployment requires explicit confirmation.');
        }

        if ($policy->environment === 'production' && ($data['typed_website_name'] ?? null) !== $website->name) {
            throw new OperationBlockedException('Type the website name exactly before deploying production.');
        }

        if (($data['password'] ?? null) && ! Hash::check((string) $data['password'], (string) $user->password)) {
            throw new OperationBlockedException('Password confirmation failed.');
        }

        $deployment = Deployment::query()->create([
            'website_id' => $website->id,
            'website_component_id' => $component?->id,
            'coolify_resource_link_id' => $link->id,
            'provider' => DeploymentProvider::Coolify,
            'trigger' => DeploymentTrigger::from((string) ($data['trigger'] ?? DeploymentTrigger::Manual->value)),
            'requested_by' => $user->id,
            'status' => $policy->requires_approval && ! $user->isOwner() ? DeploymentStatus::AwaitingApproval : DeploymentStatus::Queued,
            'branch' => $branch,
            'commit_sha' => $data['commit_sha'] ?? null,
            'commit_message' => isset($data['commit_message']) ? $this->redactor->redact((string) $data['commit_message']) : null,
            'preflight' => $preflight,
            'metadata' => ['environment' => $policy->environment],
        ]);

        if ($deployment->status === DeploymentStatus::AwaitingApproval) {
            $approval = DeploymentApproval::query()->create([
                'deployment_id' => $deployment->id,
                'required_by_policy' => true,
                'requested_by' => $user->id,
                'status' => DeploymentApprovalStatus::Pending,
                'expires_at' => now()->addHour(),
                'approval_fingerprint' => $this->fingerprint($deployment),
            ]);
            Notification::send(User::query()->where('role', 'owner')->where('is_active', true)->get(), new YouPanelNotification('Deployment approval needed', $website->name.' is waiting for approval.', 'warning', '/deployments/'.$deployment->uuid, ['deployment_uuid' => $deployment->uuid, 'approval_id' => $approval->id]));
        } else {
            RunCoolifyDeploymentJob::dispatch($deployment->id);
        }

        $this->auditLogger->record('deployment.requested', $user, $website, ['deployment_uuid' => $deployment->uuid, 'resource_link_id' => $link->id]);

        return $deployment->refresh();
    }

    public function run(Deployment $deployment): void
    {
        $lock = Cache::lock('deployment:resource:'.$deployment->coolify_resource_link_id, (int) config('coolify.deployment_timeout_minutes') * 60);

        if (! $lock->get()) {
            $deployment->update(['status' => DeploymentStatus::Failed, 'failure_reason' => 'Another deployment is already running for this resource.', 'finished_at' => now()]);

            return;
        }

        try {
            $this->capabilities->assertSupported('deploy.trigger');
            $deployment->update(['status' => DeploymentStatus::Preparing, 'started_at' => now()]);
            $this->client->resource($deployment->resourceLink->resource_type->value, $deployment->resourceLink->coolify_uuid);
            $deployment->update(['status' => DeploymentStatus::Deploying]);
            $response = $this->client->deploy($deployment->resourceLink, $deployment);
            $coolifyUuid = $response['deployment_uuid'] ?? $response['deployments'][0]['deployment_uuid'] ?? null;
            $logs = $coolifyUuid ? $this->client->deploymentLogs((string) $coolifyUuid) : ['logs' => $response['message'] ?? 'Deployment requested.', 'complete' => true];
            $output = $this->redactor->redact((string) ($logs['logs'] ?? ''));
            $path = $this->storeLogs($deployment, $output);
            $status = (($response['status'] ?? null) === 'failed') ? DeploymentStatus::Failed : DeploymentStatus::Succeeded;

            $deployment->update([
                'coolify_deployment_uuid' => $coolifyUuid,
                'status' => $status,
                'finished_at' => now(),
                'duration_seconds' => now()->diffInSeconds($deployment->started_at),
                'logs_storage_path' => $path,
                'logs_preview' => substr($output, 0, (int) config('coolify.log_max_bytes')),
                'failure_reason' => $status === DeploymentStatus::Failed ? ($response['message'] ?? 'Coolify deployment failed.') : null,
            ]);

            $this->auditLogger->record($status === DeploymentStatus::Succeeded ? 'deployment.completed' : 'deployment.failed', $deployment->requester, $deployment->website, ['deployment_uuid' => $deployment->uuid]);
            Notification::send($deployment->website->members()->get()->push($deployment->requester)->unique('id'), new YouPanelNotification('Deployment '.$status->value, $deployment->website->name.' deployment '.$status->value.'.', $status === DeploymentStatus::Succeeded ? 'success' : 'danger', '/deployments/'.$deployment->uuid));
        } catch (\Throwable $exception) {
            $deployment->update(['status' => DeploymentStatus::Failed, 'finished_at' => now(), 'failure_reason' => $this->redactor->redact($exception->getMessage())]);
            $this->auditLogger->record('deployment.failed', $deployment->requester, $deployment->website, ['deployment_uuid' => $deployment->uuid, 'reason' => $this->redactor->redact($exception->getMessage())]);
        } finally {
            $lock->release();
        }
    }

    public function approve(Deployment $deployment, User $owner): Deployment
    {
        if (! $owner->isOwner()) {
            throw new OperationBlockedException('Only owners can approve deployments.');
        }

        $approval = $deployment->approval;
        if (! $approval || $approval->status !== DeploymentApprovalStatus::Pending) {
            throw new OperationBlockedException('This deployment is not awaiting approval.');
        }

        if ($approval->expires_at && $approval->expires_at->isPast()) {
            $approval->update(['status' => DeploymentApprovalStatus::Expired]);
            throw new OperationBlockedException('This approval request has expired.');
        }

        if ($approval->approval_fingerprint !== $this->fingerprint($deployment)) {
            $approval->update(['status' => DeploymentApprovalStatus::Invalidated]);
            throw new OperationBlockedException('Deployment details changed after approval was requested.');
        }

        $approval->update(['status' => DeploymentApprovalStatus::Approved, 'approved_by' => $owner->id, 'approved_at' => now()]);
        $deployment->update(['status' => DeploymentStatus::Queued]);
        $this->auditLogger->record('deployment.approved', $owner, $deployment->website, ['deployment_uuid' => $deployment->uuid]);
        RunCoolifyDeploymentJob::dispatch($deployment->id);

        return $deployment->refresh();
    }

    public function reject(Deployment $deployment, User $owner, ?string $reason = null): Deployment
    {
        if (! $owner->isOwner()) {
            throw new OperationBlockedException('Only owners can reject deployments.');
        }

        $deployment->approval?->update(['status' => DeploymentApprovalStatus::Rejected, 'approved_by' => $owner->id, 'reason' => $reason]);
        $deployment->update(['status' => DeploymentStatus::Cancelled, 'finished_at' => now(), 'failure_reason' => $reason ?? 'Deployment rejected.']);
        $this->auditLogger->record('deployment.rejected', $owner, $deployment->website, ['deployment_uuid' => $deployment->uuid]);

        return $deployment->refresh();
    }

    public function cancel(Deployment $deployment, User $user): Deployment
    {
        if (! $user->isOwner() && $deployment->requested_by !== $user->id) {
            throw new OperationBlockedException('You cannot cancel this deployment.');
        }

        if ($deployment->coolify_deployment_uuid) {
            $this->client->cancelDeployment($deployment->coolify_deployment_uuid);
        }

        $deployment->update(['status' => DeploymentStatus::Cancelled, 'finished_at' => now()]);
        $this->auditLogger->record('deployment.cancelled', $user, $deployment->website, ['deployment_uuid' => $deployment->uuid]);

        return $deployment->refresh();
    }

    /** @return array<string, mixed> */
    public function logs(Deployment $deployment): array
    {
        $logs = $deployment->coolify_deployment_uuid
            ? $this->client->deploymentLogs($deployment->coolify_deployment_uuid)
            : ['logs' => $deployment->logs_preview ?? 'No Coolify deployment identifier is available yet.', 'complete' => true];

        $text = $this->redactor->redact(substr((string) ($logs['logs'] ?? ''), 0, (int) config('coolify.log_max_bytes')));

        return ['deployment_uuid' => $deployment->uuid, 'logs' => $text, 'complete' => (bool) ($logs['complete'] ?? false), 'redacted' => $text !== ($logs['logs'] ?? '')];
    }

    /** @return array<string, mixed> */
    public function resourceAction(CoolifyResourceLink $link, User $user, string $action, bool $confirmed = false): array
    {
        if (! in_array($user->role->value, ['owner', 'developer'], true)) {
            throw new OperationBlockedException('Your role cannot control linked resources.');
        }

        if (in_array($action, ['stop', 'restart'], true) && ! $confirmed) {
            throw new OperationBlockedException('This resource action requires confirmation.');
        }

        $this->capabilities->assertSupported('applications.'.$action);
        $result = $this->client->resourceAction($link, $action);
        $this->auditLogger->record('coolify.resource.'.$action, $user, $link->website, ['resource_link_id' => $link->id]);

        return $result;
    }

    private function policyFor(Website $website, ?WebsiteComponent $component): DeploymentPolicy
    {
        return DeploymentPolicy::query()->firstOrCreate([
            'website_id' => $website->id,
            'website_component_id' => $component?->id,
            'environment' => 'production',
        ], [
            'requires_approval' => true,
            'allowed_branches' => ['main'],
            'protected_branches' => ['main'],
            'health_check_after_deploy' => true,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function preflight(Website $website, DeploymentPolicy $policy, string $branch): array
    {
        $allowed = $policy->allowed_branches ?: ['main'];
        $checks = [
            ['key' => 'branch_allowed', 'label' => 'Branch is allowed', 'passed' => in_array($branch, $allowed, true), 'detail' => 'Allowed branches: '.implode(', ', $allowed)],
            ['key' => 'backup_required', 'label' => 'Backup requirement', 'passed' => ! $policy->requires_backup || Backup::query()->whereBelongsTo($website)->where('status', 'succeeded')->where('created_at', '>=', now()->subDay())->exists(), 'detail' => $policy->requires_backup ? 'Requires a successful backup from the last 24 hours.' : 'No fresh backup required by policy.'],
            ['key' => 'approval', 'label' => 'Approval policy', 'passed' => true, 'detail' => $policy->requires_approval ? 'Owner approval is required for developers.' : 'Approval not required.'],
        ];

        if (collect($checks)->contains(fn (array $check): bool => $check['passed'] === false)) {
            throw new OperationBlockedException('Deployment preflight checks failed.', ['checks' => $checks]);
        }

        return $checks;
    }

    /** @return array<string, mixed> */
    private function fingerprint(Deployment $deployment): array
    {
        return [
            'website_id' => $deployment->website_id,
            'component_id' => $deployment->website_component_id,
            'resource_link_id' => $deployment->coolify_resource_link_id,
            'branch' => $deployment->branch,
            'commit_sha' => $deployment->commit_sha,
        ];
    }

    private function storeLogs(Deployment $deployment, string $output): string
    {
        $path = 'deployment-output/'.$deployment->website_id.'/'.$deployment->uuid.'.log';
        $absolute = storage_path('app/private/'.$path);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, substr($output, 0, (int) config('coolify.log_max_bytes')));

        return $path;
    }
}

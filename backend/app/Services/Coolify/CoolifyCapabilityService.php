<?php

namespace App\Services\Coolify;

use App\Data\Coolify\CoolifyCapabilityData;
use App\Exceptions\CoolifyUnsupportedCapabilityException;

class CoolifyCapabilityService
{
    /** @return array<int, array<string, mixed>> */
    public function capabilities(): array
    {
        return array_map(
            fn (CoolifyCapabilityData $capability): array => $capability->toArray(),
            $this->matrix()
        );
    }

    public function assertSupported(string $capability): void
    {
        $item = collect($this->capabilities())->firstWhere('capability', $capability);

        if (! $item || ! $item['supported'] || ! $item['implemented']) {
            throw new CoolifyUnsupportedCapabilityException('This action is not available through the installed Coolify API.');
        }
    }

    /** @return array<int, CoolifyCapabilityData> */
    private function matrix(): array
    {
        return [
            new CoolifyCapabilityData('connection.health', true, 'GET /health', 'none', true, 'Show offline/degraded state.'),
            new CoolifyCapabilityData('version.read', true, 'GET /version', 'read', true, 'Version is shown as unavailable.'),
            new CoolifyCapabilityData('applications.list', true, 'GET /applications', 'read', true, 'Resource discovery omits applications.'),
            new CoolifyCapabilityData('applications.get', true, 'GET /applications/{uuid}', 'read', true, 'Link verification fails safely.'),
            new CoolifyCapabilityData('applications.logs', true, 'GET /applications/{uuid}/logs', 'read or read:sensitive for full logs', true, 'Log viewer shows unavailable.'),
            new CoolifyCapabilityData('applications.start', true, 'POST /applications/{uuid}/start', 'deploy', true, 'Control is disabled.'),
            new CoolifyCapabilityData('applications.stop', true, 'POST /applications/{uuid}/stop', 'deploy', true, 'Control is disabled.'),
            new CoolifyCapabilityData('applications.restart', true, 'POST /applications/{uuid}/restart', 'deploy', true, 'Control is disabled.'),
            new CoolifyCapabilityData('deploy.trigger', true, 'POST /deploy?uuid={uuid}', 'deploy', true, 'Deployment request is rejected.'),
            new CoolifyCapabilityData('deployments.list_running', true, 'GET /deployments', 'read', true, 'Local records remain visible.'),
            new CoolifyCapabilityData('deployments.get', true, 'GET /deployments/{uuid}', 'read', true, 'Status remains unknown.'),
            new CoolifyCapabilityData('deployments.cancel', true, 'POST /deployments/{uuid}/cancel', 'deploy', true, 'Cancel is unavailable.'),
            new CoolifyCapabilityData('deployments.application_history', true, 'GET /deployments/applications/{uuid}', 'read', true, 'Only local history is shown.'),
            new CoolifyCapabilityData('projects.list', true, 'GET /projects', 'read', true, 'Project labels are unavailable.'),
            new CoolifyCapabilityData('servers.list', true, 'GET /servers', 'read', true, 'Server labels are unavailable.'),
            new CoolifyCapabilityData('services.list', true, 'GET /services', 'read', true, 'Service discovery omits services.'),
            new CoolifyCapabilityData('databases.list', true, 'GET /databases', 'read', true, 'Database discovery omits databases.'),
            new CoolifyCapabilityData('resources.list', true, 'GET /resources', 'read', false, 'OpenAPI marks response as complex; YouPanel uses typed list endpoints.'),
            new CoolifyCapabilityData('container.metrics', false, null, 'not documented', false, 'Show resource-level status and unavailable metrics.'),
            new CoolifyCapabilityData('container.terminal', false, null, 'not documented', false, 'Show protected Coolify terminal link only.'),
            new CoolifyCapabilityData('host.terminal', false, null, 'not exposed', false, 'No YouPanel endpoint exists.'),
            new CoolifyCapabilityData('rollback.native', false, null, 'not verified', false, 'Rollback is unavailable; redeploy is shown only for current linked resource.'),
            new CoolifyCapabilityData('webhooks.deployment', false, null, 'not verified', false, 'Use polling and manual synchronization.'),
        ];
    }
}

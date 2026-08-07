<?php

namespace App\Services\Coolify;

use App\Enums\CoolifyResourceType;
use App\Exceptions\OperationBlockedException;
use App\Models\CoolifyResourceLink;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\AuditLogger;

class CoolifyLinkService
{
    public function __construct(
        private readonly CoolifySynchronizationService $sync,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Website $website, User $user, array $data): CoolifyResourceLink
    {
        if (! $user->isOwner()) {
            throw new OperationBlockedException('Only owners can link Coolify resources.');
        }

        $component = isset($data['website_component_id'])
            ? WebsiteComponent::query()->whereBelongsTo($website)->findOrFail($data['website_component_id'])
            : null;
        $type = CoolifyResourceType::from((string) $data['resource_type']);
        $resource = $this->sync->verifyLinkResource($type, (string) $data['coolify_uuid']);

        $existing = CoolifyResourceLink::query()
            ->where('resource_type', $type)
            ->where('coolify_uuid', $resource['coolify_uuid'])
            ->first();

        if ($existing && $existing->website_id !== $website->id) {
            throw new OperationBlockedException('This Coolify resource is already linked to a different website.');
        }

        $link = CoolifyResourceLink::query()->updateOrCreate([
            'resource_type' => $type,
            'coolify_uuid' => $resource['coolify_uuid'],
        ], [
            'website_id' => $website->id,
            'website_component_id' => $component?->id,
            'server_id' => $website->server_id,
            'coolify_project_uuid' => $resource['project_uuid'] ?? null,
            'coolify_environment_uuid' => $resource['environment_uuid'] ?? null,
            'display_name' => $data['display_name'] ?? $resource['display_name'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'is_active' => true,
            'last_synced_at' => now(),
            'last_status' => $resource['status'] ?? 'unknown',
            'metadata' => $resource,
        ]);

        $this->auditLogger->record('coolify.link.created', $user, $website, ['target_type' => 'coolify_resource', 'target_identifier' => $link->coolify_uuid]);

        return $link->refresh();
    }

    public function remove(CoolifyResourceLink $link, User $user): void
    {
        if (! $user->isOwner()) {
            throw new OperationBlockedException('Only owners can remove Coolify links.');
        }

        $website = $link->website;
        $uuid = $link->coolify_uuid;
        $link->delete();
        $this->auditLogger->record('coolify.link.removed', $user, $website, ['target_type' => 'coolify_resource', 'target_identifier' => $uuid, 'coolify_resource_deleted' => false]);
    }
}

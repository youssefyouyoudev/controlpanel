<?php

namespace App\Services\Coolify;

use App\Contracts\CoolifyClientInterface;
use App\Enums\CoolifyResourceType;
use App\Enums\CoolifySyncStatus;
use App\Models\CoolifyResourceLink;
use App\Models\CoolifySyncRun;
use App\Models\User;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

class CoolifySynchronizationService
{
    public function __construct(
        private readonly CoolifyClientInterface $client,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function synchronize(?User $user = null, ?Website $website = null): CoolifySyncRun
    {
        $lock = Cache::lock('coolify:sync:'.($website?->id ?? 'all'), 300);

        if (! $lock->get()) {
            return CoolifySyncRun::query()->create([
                'requested_by' => $user?->id,
                'website_id' => $website?->id,
                'status' => CoolifySyncStatus::Locked,
                'started_at' => now(),
                'finished_at' => now(),
                'errors' => ['Synchronization is already running.'],
            ]);
        }

        $run = CoolifySyncRun::query()->create([
            'requested_by' => $user?->id,
            'website_id' => $website?->id,
            'status' => CoolifySyncStatus::Running,
            'started_at' => now(),
            'errors' => [],
        ]);

        try {
            $links = CoolifyResourceLink::query()
                ->when($website, fn ($query) => $query->whereBelongsTo($website))
                ->where('is_active', true)
                ->get();
            $updated = 0;
            $errors = [];

            foreach ($links as $link) {
                try {
                    $resource = $this->client->resource($link->resource_type->value, $link->coolify_uuid);
                    $link->update([
                        'display_name' => $resource['display_name'] ?? $link->display_name,
                        'last_status' => $resource['status'] ?? 'unknown',
                        'last_synced_at' => now(),
                        'metadata' => $resource,
                    ]);
                    $updated++;
                } catch (\Throwable $exception) {
                    $link->update(['last_status' => 'missing', 'last_synced_at' => now()]);
                    $errors[] = $link->coolify_uuid.': '.$exception->getMessage();
                }
            }

            $allResources = collect($this->client->resources())
                ->reject(fn (array $resource): bool => $links->contains(fn (CoolifyResourceLink $link): bool => $link->coolify_uuid === $resource['coolify_uuid'] && $link->resource_type->value === $resource['resource_type']));

            $run->update([
                'status' => $errors === [] ? CoolifySyncStatus::Succeeded : CoolifySyncStatus::Partial,
                'finished_at' => now(),
                'updated_links' => $updated,
                'unmatched_resources' => $allResources->count(),
                'errors' => $errors,
            ]);

            if ($user) {
                $this->auditLogger->record('coolify.synchronized', $user, $website, ['updated_links' => $updated, 'unmatched_resources' => $allResources->count()]);
            }
        } catch (\Throwable $exception) {
            $run->update(['status' => CoolifySyncStatus::Failed, 'finished_at' => now(), 'errors' => [$exception->getMessage()]]);
        } finally {
            $lock->release();
        }

        return $run->refresh();
    }

    public function verifyLinkResource(CoolifyResourceType $type, string $uuid): array
    {
        return $this->client->resource($type->value, $uuid) ?? [];
    }
}

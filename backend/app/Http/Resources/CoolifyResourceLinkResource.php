<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoolifyResourceLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'website_id' => $this->website_id,
            'component' => $this->whenLoaded('component', fn (): WebsiteComponentResource => new WebsiteComponentResource($this->component)),
            'resource_type' => $this->resource_type?->value ?? $this->resource_type,
            'coolify_uuid' => $this->coolify_uuid,
            'display_name' => $this->display_name,
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'last_status' => $this->last_status,
            'domains' => $metadata['domains'] ?? [],
            'project' => $metadata['project'] ?? null,
            'environment' => $metadata['environment'] ?? null,
            'image' => $metadata['image'] ?? null,
            'restart_count' => $metadata['restart_count'] ?? null,
            'metrics' => $metadata['metrics'] ?? ['cpu' => null, 'memory' => null],
            'open_url' => $metadata['open_url'] ?? null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

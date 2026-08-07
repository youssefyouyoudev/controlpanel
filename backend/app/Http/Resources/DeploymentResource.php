<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'website_id' => $this->website_id,
            'website' => $this->whenLoaded('website', fn (): WebsiteResource => new WebsiteResource($this->website)),
            'component' => $this->whenLoaded('component', fn (): WebsiteComponentResource => new WebsiteComponentResource($this->component)),
            'resource_link' => $this->whenLoaded('resourceLink', fn (): CoolifyResourceLinkResource => new CoolifyResourceLinkResource($this->resourceLink)),
            'provider' => $this->provider?->value ?? $this->provider,
            'trigger' => $this->trigger?->value ?? $this->trigger,
            'requested_by' => $this->requested_by,
            'requester' => $this->whenLoaded('requester', fn (): UserResource => new UserResource($this->requester)),
            'status' => $this->status?->value ?? $this->status,
            'commit_sha' => $this->commit_sha,
            'commit_message' => $this->commit_message,
            'branch' => $this->branch,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'duration_seconds' => $this->duration_seconds,
            'deployment_url' => $this->deployment_url,
            'logs_preview' => $this->logs_preview,
            'failure_reason' => $this->failure_reason,
            'preflight' => $this->preflight ?? [],
            'approval' => $this->whenLoaded('approval', fn (): ?DeploymentApprovalResource => $this->approval ? new DeploymentApprovalResource($this->approval) : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

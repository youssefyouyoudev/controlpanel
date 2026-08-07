<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActionExecutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'website_id' => $this->website_id,
            'website' => $this->whenLoaded('website', fn (): WebsiteResource => new WebsiteResource($this->website)),
            'component' => $this->whenLoaded('component', fn (): WebsiteComponentResource => new WebsiteComponentResource($this->component)),
            'action_key' => $this->action_key,
            'requested_by' => $this->requested_by,
            'requester' => $this->whenLoaded('requester', fn (): UserResource => new UserResource($this->requester)),
            'status' => $this->status?->value ?? $this->status,
            'risk_level' => $this->risk_level?->value ?? $this->risk_level,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'exit_code' => $this->exit_code,
            'summary' => $this->summary,
            'output_preview' => $this->output_preview,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsoleExecutionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'website_id' => $this->website_id,
            'component' => $this->whenLoaded('component', fn (): WebsiteComponentResource => new WebsiteComponentResource($this->component)),
            'requested_by' => $this->requested_by,
            'command_alias' => $this->command_alias,
            'status' => $this->status?->value ?? $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'exit_code' => $this->exit_code,
            'output_preview' => $this->output_preview,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

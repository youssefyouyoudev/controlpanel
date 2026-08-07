<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'website_id' => $this->website_id,
            'component' => $this->whenLoaded('component', fn (): WebsiteComponentResource => new WebsiteComponentResource($this->component)),
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'requested_by' => $this->requested_by,
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

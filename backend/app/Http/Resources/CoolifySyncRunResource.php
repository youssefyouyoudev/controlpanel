<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoolifySyncRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'website_id' => $this->website_id,
            'resource_type' => $this->resource_type,
            'status' => $this->status?->value ?? $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_links' => $this->created_links,
            'updated_links' => $this->updated_links,
            'unmatched_resources' => $this->unmatched_resources,
            'errors' => $this->errors ?? [],
        ];
    }
}

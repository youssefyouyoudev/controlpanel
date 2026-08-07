<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'hostname' => $this->hostname,
            'description' => $this->description,
            'operating_system' => $this->operating_system,
            'is_local' => (bool) $this->is_local,
            'status' => $this->status?->value,
            'last_seen_at' => $this->last_seen_at?->toISOString(),
        ];
    }
}

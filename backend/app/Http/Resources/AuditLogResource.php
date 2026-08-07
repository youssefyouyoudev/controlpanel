<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_identifier' => $this->target_identifier,
            'website' => $this->whenLoaded('website', fn (): WebsiteResource => new WebsiteResource($this->website)),
            'user' => $this->whenLoaded('user', fn (): UserResource => new UserResource($this->user)),
            'request_id' => $this->request_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

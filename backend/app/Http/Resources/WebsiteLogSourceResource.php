<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteLogSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'website_id' => $this->website_id,
            'component' => $this->whenLoaded('component', fn (): WebsiteComponentResource => new WebsiteComponentResource($this->component)),
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'download_enabled' => (bool) $this->download_enabled && $request->user()?->isOwner(),
            'is_sensitive' => (bool) $this->is_sensitive,
            'is_active' => (bool) $this->is_active,
        ];
    }
}

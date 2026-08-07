<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrashEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'website_id' => $this->website_id,
            'allowed_path_id' => $this->allowed_path_id,
            'original_relative_path' => $this->original_relative_path,
            'item_type' => $this->item_type,
            'original_size' => $this->original_size,
            'checksum' => $this->checksum,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'restored_at' => $this->restored_at?->toISOString(),
        ];
    }
}

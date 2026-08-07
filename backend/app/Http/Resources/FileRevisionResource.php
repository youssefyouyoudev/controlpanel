<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileRevisionResource extends JsonResource
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
            'relative_path' => $this->relative_path,
            'operation' => $this->operation,
            'original_size' => $this->original_size,
            'new_size' => $this->new_size,
            'original_checksum' => $this->original_checksum,
            'new_checksum' => $this->new_checksum,
            'user' => $this->whenLoaded('user', fn (): UserResource => new UserResource($this->user)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

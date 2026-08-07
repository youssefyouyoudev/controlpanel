<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'website_id' => $this->website_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type?->value ?? $this->type,
            'relative_working_directory' => $this->relative_working_directory,
            'runtime' => $this->runtime,
            'process_manager' => $this->process_manager,
            'process_name' => $request->user()?->isOwner() ? $this->process_name : null,
            'build_command_key' => $this->build_command_key,
            'start_command_key' => $this->start_command_key,
            'health_url' => $request->user()?->isOwner() ? $this->health_url : null,
            'status' => $this->status?->value ?? $this->status,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

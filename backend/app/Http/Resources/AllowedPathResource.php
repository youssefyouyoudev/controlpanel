<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllowedPathResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'website_id' => $this->website_id,
            'name' => $this->name,
            'label' => $this->relative_label ?: $this->name,
            'absolute_path' => $this->when($user?->isOwner(), $this->absolute_path),
            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
            'diagnostics' => $this->metadata['diagnostics'] ?? null,
            'capabilities' => [
                'read' => (bool) $this->can_read,
                'write' => (bool) $this->can_write,
                'upload' => (bool) $this->can_upload,
                'create' => (bool) $this->can_create,
                'rename' => (bool) $this->can_rename,
                'move' => (bool) $this->can_move,
                'copy' => (bool) $this->can_copy,
                'delete' => (bool) $this->can_delete,
                'archive' => (bool) $this->can_archive,
                'extract' => (bool) $this->can_extract,
            ],
            'max_upload_bytes' => $this->max_upload_bytes,
            'allowed_extensions' => $this->allowed_extensions,
            'blocked_patterns' => $this->blocked_patterns,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

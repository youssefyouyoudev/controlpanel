<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'framework' => $this->framework,
            'status' => $this->status?->value,
            'server' => $this->whenLoaded('server', fn (): ServerResource => new ServerResource($this->server)),
            'repository_url' => $this->repository_url,
            'repository_branch' => $this->repository_branch,
            'assigned_port' => $this->when($user?->isOwner(), $this->assigned_port),
            'display_path' => $this->when($user?->isOwner(), $this->root_path),
            'modules' => [
                'files' => 'Coming in the next phase',
                'logs' => 'Coming in the next phase',
                'deployments' => 'Coming in the next phase',
                'backups' => 'Coming in the next phase',
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

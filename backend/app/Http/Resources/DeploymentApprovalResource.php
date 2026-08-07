<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentApprovalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deployment_id' => $this->deployment_id,
            'required_by_policy' => $this->required_by_policy,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'status' => $this->status?->value ?? $this->status,
            'reason' => $this->reason,
            'expires_at' => $this->expires_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
        ];
    }
}

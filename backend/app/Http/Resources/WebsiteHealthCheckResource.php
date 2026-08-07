<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteHealthCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'website_id' => $this->website_id,
            'url' => $request->user()?->isOwner() ? $this->url : parse_url((string) $this->url, PHP_URL_HOST),
            'expected_status' => $this->expected_status,
            'status' => $this->status?->value ?? $this->status,
            'consecutive_failures' => $this->consecutive_failures,
            'last_checked_at' => $this->last_checked_at?->toISOString(),
            'failure_reason' => $this->failure_reason,
            'is_active' => (bool) $this->is_active,
        ];
    }
}

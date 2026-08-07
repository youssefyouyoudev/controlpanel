<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActionDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this['key'],
            'name' => $this['name'],
            'description' => $this['description'],
            'category' => $this['category'],
            'risk_level' => $this['risk_level'],
            'required_role' => $this['required_role'],
            'requires_confirmation' => (bool) $this['requires_confirmation'],
            'requires_password_confirmation' => (bool) $this['requires_password_confirmation'],
            'timeout_seconds' => (int) $this['timeout_seconds'],
            'supports_streaming' => (bool) $this['supports_streaming'],
            'enabled' => (bool) $this['enabled'],
            'backup_required' => (bool) ($this['backup_required'] ?? false),
        ];
    }
}

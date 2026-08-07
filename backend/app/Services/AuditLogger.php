<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?User $user = null, ?Website $website = null, array $metadata = [], ?Request $request = null): AuditLog
    {
        $request ??= request();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'website_id' => $website?->id,
            'action' => $action,
            'target_type' => $metadata['target_type'] ?? null,
            'target_identifier' => $metadata['target_identifier'] ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('request_id'),
            'metadata' => $this->cleanMetadata($metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function cleanMetadata(array $metadata): array
    {
        unset($metadata['password'], $metadata['password_confirmation'], $metadata['current_password'], $metadata['token']);

        return $metadata;
    }
}

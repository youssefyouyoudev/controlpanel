<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupScheduleResource extends JsonResource
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
            'backup_type' => $this->backup_type?->value ?? $this->backup_type,
            'cron_expression' => $this->cron_expression,
            'retention_count' => $this->retention_count,
            'retention_days' => $this->retention_days,
            'is_enabled' => (bool) $this->is_enabled,
            'last_run_at' => $this->last_run_at?->toISOString(),
            'next_run_at' => $this->next_run_at?->toISOString(),
        ];
    }
}

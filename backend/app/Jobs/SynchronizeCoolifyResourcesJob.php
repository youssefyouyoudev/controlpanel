<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Website;
use App\Services\Coolify\CoolifySynchronizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SynchronizeCoolifyResourcesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?int $userId = null, public readonly ?int $websiteId = null) {}

    public function handle(CoolifySynchronizationService $sync): void
    {
        $sync->synchronize(
            $this->userId ? User::query()->find($this->userId) : null,
            $this->websiteId ? Website::query()->find($this->websiteId) : null,
        );
    }
}

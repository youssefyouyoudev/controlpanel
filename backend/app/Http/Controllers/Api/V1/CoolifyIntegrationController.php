<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CoolifyClientInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\CoolifySyncRunResource;
use App\Jobs\SynchronizeCoolifyResourcesJob;
use App\Models\CoolifySyncRun;
use App\Services\Coolify\CoolifyCapabilityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoolifyIntegrationController extends Controller
{
    public function __construct(
        private readonly CoolifyClientInterface $coolify,
        private readonly CoolifyCapabilityService $capabilities,
    ) {}

    public function status(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        try {
            $status = $this->coolify->status();
        } catch (\Throwable $exception) {
            $status = [
                'enabled' => (bool) config('coolify.enabled'),
                'driver' => config('coolify.driver'),
                'connected' => false,
                'version' => null,
                'health' => 'unreachable',
                'message' => $exception->getMessage(),
            ];
        }

        $lastSync = CoolifySyncRun::query()->latest()->first();

        return ApiResponse::success([
            ...$status,
            'internal_url' => config('coolify.internal_url'),
            'public_url' => config('coolify.public_url'),
            'token_configured' => filled(config('coolify.api_token')),
            'terminal_enabled' => false,
            'last_synchronization' => $lastSync ? new CoolifySyncRunResource($lastSync) : null,
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        return ApiResponse::success(['connection' => $this->coolify->status()], 'Coolify connection checked.');
    }

    public function capabilities(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        return ApiResponse::success($this->capabilities->capabilities());
    }

    public function synchronize(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        SynchronizeCoolifyResourcesJob::dispatch($request->user()->id);

        return ApiResponse::success(['queued' => true], 'Coolify synchronization queued.', 202);
    }

    public function resources(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $type = $request->query('type');

        return ApiResponse::success($this->coolify->resources(is_string($type) ? $type : null));
    }
}

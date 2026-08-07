<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreHealthCheckRequest;
use App\Http\Resources\WebsiteHealthCheckResource;
use App\Jobs\RunWebsiteHealthCheckJob;
use App\Models\Website;
use App\Models\WebsiteHealthCheckResult;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteHealthController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $check = $website->healthChecks()->latest()->first();

        return ApiResponse::success(['health' => $check ? new WebsiteHealthCheckResource($check) : null]);
    }

    public function store(StoreHealthCheckRequest $request, Website $website): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure health checks.');
        $check = $website->healthChecks()->create($request->validated());
        $this->auditLogger->record('health_check.created', $request->user(), $website, ['target_type' => 'health_check', 'target_identifier' => (string) $check->id]);

        return ApiResponse::success(['health' => new WebsiteHealthCheckResource($check)], 'Health check created.', 201);
    }

    public function check(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $check = $website->healthChecks()->where('is_active', true)->latest()->firstOrFail();
        RunWebsiteHealthCheckJob::dispatch($check->id);

        return ApiResponse::success(['queued' => true], 'Health check queued.', 202);
    }

    public function history(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $items = $website->hasMany(WebsiteHealthCheckResult::class)->latest('checked_at')->limit(30)->get();

        return ApiResponse::success($items->map(fn ($item): array => [
            'status' => $item->status?->value ?? $item->status,
            'http_status' => $item->http_status,
            'response_time_ms' => $item->response_time_ms,
            'failure_reason' => $item->failure_reason,
            'checked_at' => $item->checked_at?->toISOString(),
        ])->all());
    }
}

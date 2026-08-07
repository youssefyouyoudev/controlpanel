<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WebsiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\DeploymentResource;
use App\Http\Resources\WebsiteResource;
use App\Models\AuditLog;
use App\Models\CoolifyResourceLink;
use App\Models\Deployment;
use App\Models\Website;
use App\Services\Coolify\CoolifyCapabilityService;
use App\Services\Metrics\ServerMetricsProvider;
use App\Services\ServiceStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ServerMetricsProvider $metrics,
        private readonly ServiceStatusService $services,
        private readonly CoolifyCapabilityService $coolifyCapabilities,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', Website::class);

        $websites = Website::query()->with('server')->visibleTo($request->user())->latest('updated_at')->get();
        $metrics = $this->metrics->collect();
        $services = $this->services->all();
        $activity = AuditLog::query()
            ->with(['user', 'website.server'])
            ->visibleTo($request->user())
            ->latest('created_at')
            ->limit(8)
            ->get();
        $deployments = Deployment::query()->visibleTo($request->user())->with(['website.server', 'component', 'resourceLink', 'requester', 'approval'])->latest()->limit(5)->get();
        $linkedResources = CoolifyResourceLink::query()->visibleTo($request->user())->where('is_active', true)->count();
        $deploymentStatuses = $deployments->map(fn (Deployment $deployment): string => $deployment->status?->value ?? (string) $deployment->status);

        return ApiResponse::success([
            'server' => [
                'status' => $this->serverStatus($metrics, $services),
                'hostname' => $metrics['hostname'] ?? null,
            ],
            'metrics' => $metrics,
            'website_counts' => [
                'total' => $websites->count(),
                'healthy' => $websites->where('status', WebsiteStatus::Healthy)->count(),
                'degraded' => $websites->where('status', WebsiteStatus::Degraded)->count(),
                'offline' => $websites->where('status', WebsiteStatus::Offline)->count(),
            ],
            'services' => $services,
            'websites' => WebsiteResource::collection($websites->take(6))->resolve($request),
            'activity' => AuditLogResource::collection($activity)->resolve($request),
            'coolify' => [
                'enabled' => (bool) config('coolify.enabled'),
                'driver' => config('coolify.driver'),
                'public_url' => $request->user()->isOwner() ? config('coolify.public_url') : null,
                'linked_resources' => $linkedResources,
                'container_metrics_supported' => collect($this->coolifyCapabilities->capabilities())->firstWhere('capability', 'container.metrics')['supported'] ?? false,
            ],
            'deployments' => [
                'active' => $deploymentStatuses->filter(fn (string $status): bool => in_array($status, ['queued', 'preparing', 'building', 'deploying', 'running'], true))->count(),
                'awaiting_approval' => $deploymentStatuses->filter(fn (string $status): bool => $status === 'awaiting_approval')->count(),
                'failed' => $deploymentStatuses->filter(fn (string $status): bool => $status === 'failed')->count(),
                'latest' => DeploymentResource::collection($deployments)->resolve($request),
            ],
            'collected_at' => now()->toISOString(),
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', Website::class);

        return ApiResponse::success(['metrics' => $this->metrics->collect()]);
    }

    public function services(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', Website::class);

        return ApiResponse::success(['services' => $this->services->all()]);
    }

    public function websites(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', Website::class);

        $websites = Website::query()
            ->with('server')
            ->visibleTo($request->user())
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 12));

        return ApiResponse::success(WebsiteResource::collection($websites->getCollection())->resolve($request), meta: [
            'pagination' => [
                'current_page' => $websites->currentPage(),
                'per_page' => $websites->perPage(),
                'total' => $websites->total(),
                'last_page' => $websites->lastPage(),
            ],
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with(['user', 'website.server'])
            ->visibleTo($request->user())
            ->latest('created_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success(AuditLogResource::collection($logs->getCollection())->resolve($request), meta: [
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<int, array<string, mixed>>  $services
     */
    private function serverStatus(array $metrics, array $services): string
    {
        if (($metrics['available'] ?? false) === false) {
            return 'unknown';
        }

        if (collect($services)->contains('status', 'degraded')) {
            return 'degraded';
        }

        return 'healthy';
    }
}

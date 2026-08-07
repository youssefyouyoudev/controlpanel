<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coolify\RequestDeploymentRequest;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use App\Models\Website;
use App\Services\Coolify\DeploymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeploymentController extends Controller
{
    public function __construct(private readonly DeploymentService $deployments) {}

    public function index(Request $request): JsonResponse
    {
        $items = Deployment::query()->visibleTo($request->user())->with(['website.server', 'component', 'resourceLink', 'requester', 'approval'])->latest()->paginate(20);

        return ApiResponse::success(DeploymentResource::collection($items->getCollection())->resolve($request), meta: ['pagination' => ['current_page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total()]]);
    }

    public function show(Request $request, Deployment $deployment): JsonResponse
    {
        $this->authorize('view', $deployment);

        return ApiResponse::success(['deployment' => new DeploymentResource($deployment->load(['website.server', 'component', 'resourceLink', 'requester', 'approval']))]);
    }

    public function store(RequestDeploymentRequest $request, Website $website): JsonResponse
    {
        $this->authorize('create', [Deployment::class, $website]);

        $deployment = $this->deployments->request($website, $request->user(), $request->validated());

        return ApiResponse::success(['deployment' => new DeploymentResource($deployment->load(['website', 'component', 'resourceLink', 'approval']))], 'Deployment requested.', 202);
    }

    public function approve(Request $request, Deployment $deployment): JsonResponse
    {
        $this->authorize('approve', $deployment);

        return ApiResponse::success(['deployment' => new DeploymentResource($this->deployments->approve($deployment->load('approval'), $request->user())->load(['website', 'component', 'resourceLink', 'approval']))], 'Deployment approved.');
    }

    public function reject(Request $request, Deployment $deployment): JsonResponse
    {
        $this->authorize('approve', $deployment);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::success(['deployment' => new DeploymentResource($this->deployments->reject($deployment->load('approval'), $request->user(), $data['reason'] ?? null)->load(['website', 'component', 'resourceLink', 'approval']))], 'Deployment rejected.');
    }

    public function cancel(Request $request, Deployment $deployment): JsonResponse
    {
        $this->authorize('cancel', $deployment);

        return ApiResponse::success(['deployment' => new DeploymentResource($this->deployments->cancel($deployment, $request->user())->load(['website', 'component', 'resourceLink', 'approval']))], 'Deployment cancelled.');
    }

    public function redeploy(RequestDeploymentRequest $request, Deployment $deployment): JsonResponse
    {
        $this->authorize('view', $deployment);

        $new = $this->deployments->request($deployment->website, $request->user(), [
            ...$request->validated(),
            'resource_link_id' => $deployment->coolify_resource_link_id,
            'trigger' => 'redeploy',
            'branch' => $deployment->branch,
            'commit_sha' => $deployment->commit_sha,
        ]);

        return ApiResponse::success(['deployment' => new DeploymentResource($new->load(['website', 'component', 'resourceLink', 'approval']))], 'Redeployment requested.', 202);
    }

    public function logs(Request $request, Deployment $deployment): JsonResponse
    {
        $this->authorize('viewLogs', $deployment);

        return ApiResponse::success($this->deployments->logs($deployment));
    }

    public function stream(Request $request, Deployment $deployment): StreamedResponse
    {
        $this->authorize('viewLogs', $deployment);
        $payload = $this->deployments->logs($deployment);

        return response()->stream(function () use ($payload): void {
            echo 'data: '.json_encode($payload)."\n\n";
        }, 200, ['Content-Type' => 'text/event-stream']);
    }
}

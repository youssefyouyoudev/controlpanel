<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coolify\ResourceActionRequest;
use App\Http\Resources\CoolifyResourceLinkResource;
use App\Models\CoolifyResourceLink;
use App\Models\Website;
use App\Services\Coolify\DeploymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoolifyResourceController extends Controller
{
    public function __construct(private readonly DeploymentService $deployments) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return ApiResponse::success(CoolifyResourceLinkResource::collection($website->coolifyResourceLinks()->with('component')->where('is_active', true)->latest()->get())->resolve($request));
    }

    public function show(Request $request, Website $website, CoolifyResourceLink $link): JsonResponse
    {
        $this->authorize('view', $link);
        abort_unless($link->website_id === $website->id, 404);

        return ApiResponse::success(['resource' => new CoolifyResourceLinkResource($link->load('component'))]);
    }

    public function start(ResourceActionRequest $request, Website $website, CoolifyResourceLink $link): JsonResponse
    {
        return $this->perform($request, $website, $link, 'start');
    }

    public function stop(ResourceActionRequest $request, Website $website, CoolifyResourceLink $link): JsonResponse
    {
        return $this->perform($request, $website, $link, 'stop');
    }

    public function restart(ResourceActionRequest $request, Website $website, CoolifyResourceLink $link): JsonResponse
    {
        return $this->perform($request, $website, $link, 'restart');
    }

    private function perform(ResourceActionRequest $request, Website $website, CoolifyResourceLink $link, string $action): JsonResponse
    {
        $this->authorize('control', $link);
        abort_unless($link->website_id === $website->id, 404);

        return ApiResponse::success(['result' => $this->deployments->resourceAction($link, $request->user(), $action, (bool) $request->boolean('confirmed'))], 'Resource action queued.');
    }
}

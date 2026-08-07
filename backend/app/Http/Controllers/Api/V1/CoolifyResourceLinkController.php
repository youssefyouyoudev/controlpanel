<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CoolifyResourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coolify\StoreCoolifyLinkRequest;
use App\Http\Resources\CoolifyResourceLinkResource;
use App\Models\CoolifyResourceLink;
use App\Models\Website;
use App\Services\Coolify\CoolifyLinkService;
use App\Services\Coolify\CoolifySynchronizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoolifyResourceLinkController extends Controller
{
    public function __construct(
        private readonly CoolifyLinkService $links,
        private readonly CoolifySynchronizationService $sync,
    ) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return ApiResponse::success(CoolifyResourceLinkResource::collection($website->coolifyResourceLinks()->with('component')->latest()->get())->resolve($request));
    }

    public function store(StoreCoolifyLinkRequest $request, Website $website): JsonResponse
    {
        $link = $this->links->create($website, $request->user(), $request->validated());

        return ApiResponse::success(['link' => new CoolifyResourceLinkResource($link->load('component'))], 'Coolify resource linked.', 201);
    }

    public function update(StoreCoolifyLinkRequest $request, Website $website, CoolifyResourceLink $link): JsonResponse
    {
        $this->authorize('update', $link);
        abort_unless($link->website_id === $website->id, 404);

        $link->update($request->safe()->only(['display_name', 'is_primary']));

        return ApiResponse::success(['link' => new CoolifyResourceLinkResource($link->refresh()->load('component'))], 'Coolify link updated.');
    }

    public function destroy(Request $request, Website $website, CoolifyResourceLink $link): JsonResponse
    {
        $this->authorize('delete', $link);
        abort_unless($link->website_id === $website->id, 404);

        $this->links->remove($link, $request->user());

        return ApiResponse::success(null, 'Coolify link removed. The Coolify resource was not deleted.');
    }

    public function verify(Request $request, Website $website, CoolifyResourceLink $link): JsonResponse
    {
        $this->authorize('view', $link);
        abort_unless($link->website_id === $website->id, 404);

        $resource = $this->sync->verifyLinkResource(CoolifyResourceType::from($link->resource_type->value), $link->coolify_uuid);
        $link->update(['last_status' => $resource['status'] ?? 'unknown', 'last_synced_at' => now(), 'metadata' => $resource]);

        return ApiResponse::success(['link' => new CoolifyResourceLinkResource($link->refresh()->load('component'))], 'Coolify link verified.');
    }
}

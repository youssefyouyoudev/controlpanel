<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebsiteResource;
use App\Models\Website;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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

    public function show(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return ApiResponse::success(['website' => new WebsiteResource($website->load('server'))]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ExecuteActionRequest;
use App\Http\Resources\ActionDefinitionResource;
use App\Http\Resources\ActionExecutionResource;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\Operations\ActionCatalog;
use App\Services\Operations\ActionExecutionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteActionController extends Controller
{
    public function __construct(private readonly ActionCatalog $catalog, private readonly ActionExecutionService $executions) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $actions = collect($this->catalog->all())->filter(function (array $definition) use ($request): bool {
            try {
                $this->catalog->assertCanRun($request->user(), $definition);

                return true;
            } catch (\Throwable) {
                return false;
            }
        })->values();

        return ApiResponse::success(ActionDefinitionResource::collection($actions)->resolve($request));
    }

    public function execute(ExecuteActionRequest $request, Website $website, string $actionKey): JsonResponse
    {
        $component = null;
        if ($request->integer('website_component_id')) {
            $component = WebsiteComponent::query()->whereBelongsTo($website)->findOrFail($request->integer('website_component_id'));
        }

        $execution = $this->executions->request($website, $request->user(), $actionKey, $component, $request->validated('options') ?? []);

        return ApiResponse::success(['execution' => new ActionExecutionResource($execution)], 'Action queued.', 202);
    }
}

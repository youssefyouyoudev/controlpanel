<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreWebsiteComponentRequest;
use App\Http\Resources\WebsiteComponentResource;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\AuditLogger;
use App\Services\Operations\OperationWorkspaceResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteComponentController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly OperationWorkspaceResolver $resolver) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return ApiResponse::success(WebsiteComponentResource::collection($website->components()->latest()->get())->resolve($request));
    }

    public function store(StoreWebsiteComponentRequest $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure components.');
        $component = $website->components()->create($request->validated());
        $this->resolver->resolve($website, $request->user(), $component);
        $this->auditLogger->record('component.created', $request->user(), $website, ['target_type' => 'component', 'target_identifier' => $component->slug]);

        return ApiResponse::success(['component' => new WebsiteComponentResource($component)], 'Component created.', 201);
    }

    public function show(Request $request, Website $website, WebsiteComponent $component): JsonResponse
    {
        $this->assertBelongs($website, $component);
        $this->authorize('view', $website);

        return ApiResponse::success(['component' => new WebsiteComponentResource($component)]);
    }

    public function update(StoreWebsiteComponentRequest $request, Website $website, WebsiteComponent $component): JsonResponse
    {
        $this->assertBelongs($website, $component);
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure components.');
        $component->update($request->validated());
        $this->resolver->resolve($website, $request->user(), $component);
        $this->auditLogger->record('component.updated', $request->user(), $website, ['target_type' => 'component', 'target_identifier' => $component->slug]);

        return ApiResponse::success(['component' => new WebsiteComponentResource($component->refresh())], 'Component updated.');
    }

    public function destroy(Request $request, Website $website, WebsiteComponent $component): JsonResponse
    {
        $this->assertBelongs($website, $component);
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure components.');
        $component->delete();
        $this->auditLogger->record('component.deleted', $request->user(), $website, ['target_type' => 'component', 'target_identifier' => $component->slug]);

        return ApiResponse::success(null, 'Component deleted.');
    }

    public function validateComponent(Request $request, Website $website, WebsiteComponent $component): JsonResponse
    {
        $this->assertBelongs($website, $component);
        $this->authorize('view', $website);
        $path = $this->resolver->resolve($website, $request->user(), $component);

        return ApiResponse::success(['diagnostics' => ['status' => 'valid', 'working_directory_available' => true, 'display_path' => $request->user()?->isOwner() ? $path : null]]);
    }

    private function assertBelongs(Website $website, WebsiteComponent $component): void
    {
        abort_unless($component->website_id === $website->id, 404);
    }
}

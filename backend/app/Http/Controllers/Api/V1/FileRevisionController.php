<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileRevisionResource;
use App\Models\FileRevision;
use App\Models\Website;
use App\Services\FileRevisionService;
use App\Services\FileWorkspaceService;
use App\Services\SecurePathResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileRevisionController extends Controller
{
    public function __construct(
        private readonly SecurePathResolver $resolver,
        private readonly FileRevisionService $revisionService,
        private readonly FileWorkspaceService $workspace,
    ) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string']]);
        $this->resolver->resolve($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], 'read');
        $revisions = FileRevision::query()
            ->with('user')
            ->whereBelongsTo($website)
            ->where('allowed_path_id', $data['allowed_path_id'])
            ->where('relative_path_hash', hash('sha256', (string) $data['path']))
            ->latest('created_at')
            ->get();

        return ApiResponse::success(FileRevisionResource::collection($revisions)->resolve($request));
    }

    public function show(Request $request, Website $website, FileRevision $revision): JsonResponse
    {
        abort_unless($revision->website_id === $website->id, 404);
        $this->resolver->resolve($website, $request->user(), $revision->allowed_path_id, $revision->relative_path, 'read');
        $content = null;
        if ($revision->storage_path && is_file(storage_path('app/private/'.$revision->storage_path))) {
            $content = file_get_contents(storage_path('app/private/'.$revision->storage_path));
        }

        return ApiResponse::success(['revision' => new FileRevisionResource($revision->load('user')), 'content' => $content]);
    }

    public function restore(Request $request, Website $website, FileRevision $revision): JsonResponse
    {
        abort_unless($revision->website_id === $website->id, 404);
        abort_unless($request->user()?->isOwner() || $request->user()?->role->value === 'developer', 403, 'Only owners and developers may restore revisions.');
        abort_unless($revision->storage_path && is_file(storage_path('app/private/'.$revision->storage_path)), 422, 'This revision does not have a stored snapshot.');

        $current = $this->resolver->resolve($website, $request->user(), $revision->allowed_path_id, $revision->relative_path, 'save');
        $this->revisionService->createSnapshot($website, $current->allowedPath, $request->user(), $revision->relative_path, 'revision-restore', $current->absolutePath);
        copy(storage_path('app/private/'.$revision->storage_path), $current->absolutePath);

        return ApiResponse::success(null, 'Revision restored.');
    }
}

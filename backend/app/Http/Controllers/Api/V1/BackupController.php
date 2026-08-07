<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BackupType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BackupResource;
use App\Models\Backup;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\AuditLogger;
use App\Services\Operations\BackupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups, private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $items = $website->backups()->with('component')->latest()->paginate(20);

        return ApiResponse::success(BackupResource::collection($items->items())->resolve($request), meta: ['pagination' => ['current_page' => $items->currentPage(), 'total' => $items->total()]]);
    }

    public function store(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'in:files,database,configuration,full,pre_deployment,pre_migration,manual'], 'website_component_id' => ['nullable', 'integer']]);
        $component = $data['website_component_id'] ?? null ? WebsiteComponent::query()->whereBelongsTo($website)->findOrFail($data['website_component_id']) : null;
        $backup = $this->backups->request($website, $request->user(), BackupType::from((string) $data['type']), $component);

        return ApiResponse::success(['backup' => new BackupResource($backup)], 'Backup queued.', 202);
    }

    public function show(Request $request, Website $website, Backup $backup): JsonResponse
    {
        $this->assertBelongs($website, $backup);
        $this->authorize('view', $website);

        return ApiResponse::success(['backup' => new BackupResource($backup->load('component'))]);
    }

    public function download(Request $request, Website $website, Backup $backup): BinaryFileResponse
    {
        $this->assertBelongs($website, $backup);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may download backups.');
        abort_unless($backup->storage_path && str_starts_with($backup->storage_path, 'backups/'), 404);
        $this->auditLogger->record('backup.downloaded', $request->user(), $website, ['target_type' => 'backup', 'target_identifier' => $backup->uuid]);

        return response()->download(storage_path('app/private/'.$backup->storage_path));
    }

    public function verify(Request $request, Website $website, Backup $backup): JsonResponse
    {
        $this->assertBelongs($website, $backup);
        $this->authorize('view', $website);

        return ApiResponse::success(['verified' => $this->backups->verify($backup)]);
    }

    public function restore(Request $request, Website $website, Backup $backup): JsonResponse
    {
        $this->assertBelongs($website, $backup);
        $data = $request->validate(['typed_website_name' => ['required', 'string'], 'password' => ['required', 'string']]);
        $stage = $this->backups->stageRestore($backup, $request->user(), (string) $data['typed_website_name'], (string) $data['password']);

        return ApiResponse::success(['staging_path' => $request->user()?->isOwner() ? $stage : null], 'Restore staged for validation.');
    }

    public function destroy(Request $request, Website $website, Backup $backup): JsonResponse
    {
        $this->assertBelongs($website, $backup);
        abort_unless($request->user()?->isOwner(), 403);
        if ($backup->storage_path && str_starts_with($backup->storage_path, 'backups/')) {
            @unlink(storage_path('app/private/'.$backup->storage_path));
        }
        $backup->delete();

        return ApiResponse::success(null, 'Backup deleted.');
    }

    private function assertBelongs(Website $website, Backup $backup): void
    {
        abort_unless($backup->website_id === $website->id, 404);
    }
}

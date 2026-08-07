<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrashEntryResource;
use App\Models\TrashEntry;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Services\SecurePathResolver;
use App\Services\TrashService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class TrashController extends Controller
{
    public function __construct(
        private readonly SecurePathResolver $resolver,
        private readonly TrashService $trash,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $entries = TrashEntry::query()->whereBelongsTo($website)->whereNull('restored_at')->latest('created_at')->get();

        return ApiResponse::success(TrashEntryResource::collection($entries)->resolve($request));
    }

    public function restore(Request $request, Website $website, TrashEntry $trashEntry): JsonResponse
    {
        abort_unless($trashEntry->website_id === $website->id, 404);
        $data = $request->validate(['restore_path' => ['nullable', 'string']]);
        $destination = $this->resolver->resolve($website, $request->user(), $trashEntry->allowed_path_id, (string) ($data['restore_path'] ?? $trashEntry->original_relative_path), 'create', true);
        $result = $this->trash->restore($trashEntry, $destination);
        $this->auditLogger->record('file.restored', $request->user(), $website, ['target_type' => $trashEntry->item_type, 'target_identifier' => $trashEntry->original_relative_path]);

        return ApiResponse::success(['result' => $result->toArray()], $result->message);
    }

    public function destroy(Request $request, Website $website, TrashEntry $trashEntry): JsonResponse
    {
        abort_unless($trashEntry->website_id === $website->id, 404);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may permanently delete trash entries.');
        $data = $request->validate(['password' => ['required', 'string']]);
        abort_unless(Hash::check((string) $data['password'], (string) $request->user()?->password), 403, 'Password confirmation failed.');

        $trashPath = storage_path('app/private/'.$trashEntry->trash_storage_path);
        is_dir($trashPath) ? File::deleteDirectory($trashPath) : File::delete($trashPath);
        $trashEntry->delete();
        $this->auditLogger->record('file.permanently_deleted', $request->user(), $website, ['target_type' => 'trash_entry', 'target_identifier' => (string) $trashEntry->id]);

        return ApiResponse::success(null, 'Trash entry permanently deleted.');
    }

    public function emptyExpired(Request $request, Website $website): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may empty expired trash.');
        $entries = TrashEntry::query()->whereBelongsTo($website)->whereNull('restored_at')->where('expires_at', '<', now())->get();
        foreach ($entries as $entry) {
            $trashPath = storage_path('app/private/'.$entry->trash_storage_path);
            is_dir($trashPath) ? File::deleteDirectory($trashPath) : File::delete($trashPath);
            $entry->delete();
        }

        return ApiResponse::success(['deleted' => $entries->count()], 'Expired trash emptied.');
    }
}

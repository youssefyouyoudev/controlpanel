<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllowedPathResource;
use App\Models\AllowedPath;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileRootController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return ApiResponse::success(AllowedPathResource::collection($website->allowedPaths()->latest()->get())->resolve($request));
    }

    public function store(Request $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure file roots.');

        $data = $this->validateRootPayload($request);
        $diagnostics = $this->diagnose($data['absolute_path']);

        if (($data['is_active'] ?? false) && $diagnostics['status'] !== 'writable' && $diagnostics['status'] !== 'readable') {
            return ApiResponse::error('An active root must be an existing readable directory.', 422, ['absolute_path' => ['The path is not a readable directory.']]);
        }

        $allowedPath = $website->allowedPaths()->create([
            ...$data,
            'metadata' => ['diagnostics' => $diagnostics],
        ]);

        $this->auditLogger->record('file_root.created', $request->user(), $website, ['target_type' => 'allowed_path', 'target_identifier' => (string) $allowedPath->id]);

        return ApiResponse::success(['root' => new AllowedPathResource($allowedPath)], 'File root created.', 201);
    }

    public function show(Request $request, Website $website, AllowedPath $allowedPath): JsonResponse
    {
        $this->assertRootBelongsToWebsite($website, $allowedPath);
        $this->authorize('view', $website);

        return ApiResponse::success(['root' => new AllowedPathResource($allowedPath)]);
    }

    public function update(Request $request, Website $website, AllowedPath $allowedPath): JsonResponse
    {
        $this->assertRootBelongsToWebsite($website, $allowedPath);
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure file roots.');

        $data = $this->validateRootPayload($request, $allowedPath);
        $diagnostics = $this->diagnose($data['absolute_path']);
        if (($data['is_active'] ?? false) && $diagnostics['status'] !== 'writable' && $diagnostics['status'] !== 'readable') {
            return ApiResponse::error('An active root must be an existing readable directory.', 422, ['absolute_path' => ['The path is not a readable directory.']]);
        }

        $allowedPath->update([...$data, 'metadata' => ['diagnostics' => $diagnostics]]);
        $this->auditLogger->record('file_root.updated', $request->user(), $website, ['target_type' => 'allowed_path', 'target_identifier' => (string) $allowedPath->id]);

        return ApiResponse::success(['root' => new AllowedPathResource($allowedPath->refresh())], 'File root updated.');
    }

    public function destroy(Request $request, Website $website, AllowedPath $allowedPath): JsonResponse
    {
        $this->assertRootBelongsToWebsite($website, $allowedPath);
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure file roots.');

        $allowedPath->delete();
        $this->auditLogger->record('file_root.deleted', $request->user(), $website, ['target_type' => 'allowed_path', 'target_identifier' => (string) $allowedPath->id]);

        return ApiResponse::success(null, 'File root configuration removed.');
    }

    public function validateRoot(Request $request, Website $website, AllowedPath $allowedPath): JsonResponse
    {
        $this->assertRootBelongsToWebsite($website, $allowedPath);
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure file roots.');

        $diagnostics = $this->diagnose($allowedPath->absolute_path);
        $allowedPath->forceFill(['metadata' => ['diagnostics' => $diagnostics]])->save();

        return ApiResponse::success(['diagnostics' => $diagnostics]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRootPayload(Request $request, ?AllowedPath $allowedPath = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relative_label' => ['nullable', 'string', 'max:120'],
            'absolute_path' => ['required', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $path = str_replace('\\', '/', (string) $value);
                if (! str_starts_with($path, '/') && preg_match('/^[A-Za-z]:\//', $path) !== 1) {
                    $fail('The path must be absolute.');
                }

                if ($this->isDangerousRoot($path)) {
                    $fail('This system path cannot be used as a YouPanel file root.');
                }
            }],
            'is_primary' => ['boolean'],
            'can_read' => ['boolean'],
            'can_write' => ['boolean'],
            'can_upload' => ['boolean'],
            'can_create' => ['boolean'],
            'can_rename' => ['boolean'],
            'can_move' => ['boolean'],
            'can_copy' => ['boolean'],
            'can_delete' => ['boolean'],
            'can_archive' => ['boolean'],
            'can_extract' => ['boolean'],
            'max_upload_bytes' => ['nullable', 'integer', 'min:1', 'max:'.config('youpanel.files.max_upload_bytes')],
            'allowed_extensions' => ['nullable', 'array'],
            'blocked_patterns' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);
    }

    private function isDangerousRoot(string $path): bool
    {
        $normalized = rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');

        if ($normalized === '' || $normalized === '/' || preg_match('/^[A-Za-z]:$/', $normalized) === 1) {
            return true;
        }

        $blocked = [
            '/boot',
            '/data/coolify',
            '/dev',
            '/etc',
            '/home',
            '/proc',
            '/root',
            '/run',
            '/sys',
            '/var/lib/docker',
            '/var/lib/mysql',
            '/var/run/docker.sock',
        ];

        foreach ($blocked as $blockedPath) {
            if ($normalized === $blockedPath || str_starts_with($normalized.'/', $blockedPath.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnose(string $path): array
    {
        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            return ['status' => 'invalid', 'readable' => false, 'writable' => false];
        }

        return [
            'status' => ! is_readable($real) ? 'unavailable' : (is_writable($real) ? 'writable' : 'readable'),
            'readable' => is_readable($real),
            'writable' => is_writable($real),
        ];
    }

    private function assertRootBelongsToWebsite(Website $website, AllowedPath $allowedPath): void
    {
        abort_unless($allowedPath->website_id === $website->id, 404);
    }
}

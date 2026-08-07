<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\ArchiveService;
use App\Services\FileWorkspaceService;
use App\Services\SecurePathResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileWorkspaceController extends Controller
{
    public function __construct(
        private readonly FileWorkspaceService $workspace,
        private readonly SecurePathResolver $resolver,
        private readonly ArchiveService $archives,
    ) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['nullable', 'string'], 'sort' => ['nullable', 'in:name,modified,size']]);

        return ApiResponse::success(['entries' => $this->workspace->list($website, $request->user(), (int) $data['allowed_path_id'], (string) ($data['path'] ?? ''), (string) ($data['sort'] ?? 'name'))]);
    }

    public function metadata(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string']]);
        $resolved = $this->resolver->resolve($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], 'metadata');

        return ApiResponse::success(['metadata' => [
            'relative_path' => $resolved->relativePath,
            'type' => $resolved->type,
            'size' => is_file($resolved->absolutePath) ? filesize($resolved->absolutePath) : null,
            'modified_at' => filemtime($resolved->absolutePath) ? date(DATE_ATOM, filemtime($resolved->absolutePath)) : null,
            'readable' => is_readable($resolved->absolutePath),
            'writable' => is_writable($resolved->absolutePath),
        ]]);
    }

    public function search(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['nullable', 'string'], 'query' => ['required', 'string', 'max:120']]);

        return ApiResponse::success(['results' => $this->workspace->search($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['query'], (string) ($data['path'] ?? ''))]);
    }

    public function content(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string']]);

        return ApiResponse::success(['file' => $this->workspace->read($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'])->toArray()]);
    }

    public function save(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string'], 'content' => ['required', 'string'], 'checksum' => ['required', 'string']]);
        $result = $this->workspace->save($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], (string) $data['content'], (string) $data['checksum']);

        return ApiResponse::success(['result' => $result->toArray()], $result->message);
    }

    public function create(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string'], 'content' => ['nullable', 'string']]);
        $result = $this->workspace->createFile($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], $data['content'] ?? null);

        return ApiResponse::success(['result' => $result->toArray()], $result->message, 201);
    }

    public function directory(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string']]);
        $result = $this->workspace->createDirectory($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path']);

        return ApiResponse::success(['result' => $result->toArray()], $result->message, 201);
    }

    public function upload(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'directory' => ['nullable', 'string'], 'file' => ['required', 'file'], 'overwrite' => ['boolean']]);
        $result = $this->workspace->upload($website, $request->user(), (int) $data['allowed_path_id'], (string) ($data['directory'] ?? ''), $request->file('file'), (bool) ($data['overwrite'] ?? false));

        return ApiResponse::success(['result' => $result->toArray()], $result->message, 201);
    }

    public function download(Request $request, Website $website): BinaryFileResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string']]);
        $resolved = $this->resolver->resolve($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], 'download');
        abort_unless($resolved->type === 'file', 404);
        abort_if(filesize($resolved->absolutePath) > (int) config('youpanel.files.max_download_bytes'), 413, 'The file is too large to download through YouPanel.');

        return response()->download($resolved->absolutePath, basename($resolved->relativePath), ['X-Request-Id' => (string) $request->attributes->get('request_id')]);
    }

    public function rename(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string'], 'name' => ['required', 'string']]);
        $result = $this->workspace->rename($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], (string) $data['name']);

        return ApiResponse::success(['result' => $result->toArray()], $result->message);
    }

    public function copy(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string'], 'destination' => ['required', 'string']]);
        $result = $this->workspace->copy($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], (string) $data['destination']);

        return ApiResponse::success(['result' => $result->toArray()], $result->message);
    }

    public function move(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string'], 'destination' => ['required', 'string']]);
        $result = $this->workspace->move($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], (string) $data['destination']);

        return ApiResponse::success(['result' => $result->toArray()], $result->message);
    }

    public function archive(Request $request, Website $website): BinaryFileResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string']]);
        $resolved = $this->resolver->resolve($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], 'archive');
        $zip = $this->archives->createZip($resolved);

        return response()->download($zip, basename($resolved->relativePath ?: 'workspace').'.zip')->deleteFileAfterSend();
    }

    public function extract(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate([
            'allowed_path_id' => ['required', 'integer'],
            'path' => ['required', 'string'],
            'archive' => ['required', 'file', 'mimes:zip'],
            'overwrite' => ['boolean'],
        ]);

        /** @var UploadedFile $archive */
        $archive = $request->file('archive');
        abort_if($archive->getSize() > (int) config('youpanel.files.max_upload_bytes'), 413, 'The archive is too large to upload through YouPanel.');

        $destination = $this->resolver->resolve($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path'], 'extract');
        abort_unless($destination->type === 'dir', 422, 'Archives can only be extracted into directories.');

        $result = $this->archives->extractZip($archive->getRealPath() ?: $archive->path(), $destination, (bool) ($data['overwrite'] ?? false));

        return ApiResponse::success(['result' => $result->toArray()], $result->message);
    }

    public function delete(Request $request, Website $website): JsonResponse
    {
        $data = $request->validate(['allowed_path_id' => ['required', 'integer'], 'path' => ['required', 'string']]);
        $result = $this->workspace->trash($website, $request->user(), (int) $data['allowed_path_id'], (string) $data['path']);

        return ApiResponse::success(['result' => $result->toArray()], $result->message);
    }
}

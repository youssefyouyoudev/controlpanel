<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreLogSourceRequest;
use App\Http\Resources\WebsiteLogSourceResource;
use App\Models\Website;
use App\Models\WebsiteLogSource;
use App\Services\AuditLogger;
use App\Services\Operations\LogReaderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    public function __construct(private readonly LogReaderService $logs, private readonly AuditLogger $auditLogger) {}

    public function sources(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return ApiResponse::success(WebsiteLogSourceResource::collection($website->logSources()->with('component')->where('is_active', true)->get())->resolve($request));
    }

    public function storeSource(StoreLogSourceRequest $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure log sources.');
        $source = $website->logSources()->create($request->validated());
        $this->auditLogger->record('log_source.created', $request->user(), $website, ['target_type' => 'log_source', 'target_identifier' => $source->slug]);

        return ApiResponse::success(['source' => new WebsiteLogSourceResource($source)], 'Log source created.', 201);
    }

    public function show(Request $request, Website $website, WebsiteLogSource $source): JsonResponse
    {
        $data = $request->validate(['lines' => ['nullable', 'integer', 'min:1', 'max:500'], 'search' => ['nullable', 'string', 'max:120'], 'level' => ['nullable', 'string', 'max:20']]);

        return ApiResponse::success($this->logs->read($website, $request->user(), $source, (int) ($data['lines'] ?? config('youpanel.logs.initial_lines')), $data['search'] ?? null, $data['level'] ?? null));
    }

    public function stream(Request $request, Website $website, WebsiteLogSource $source): StreamedResponse
    {
        $payload = $this->logs->read($website, $request->user(), $source, (int) config('youpanel.logs.initial_lines'));

        return response()->stream(function () use ($payload): void {
            echo 'data: '.json_encode($payload)."\n\n";
            ob_flush();
            flush();
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
    }
}

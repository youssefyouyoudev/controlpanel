<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActionExecutionResource;
use App\Models\ActionExecution;
use App\Services\Operations\ActionExecutionService;
use App\Services\Operations\SecretRedactor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActionExecutionController extends Controller
{
    public function __construct(private readonly ActionExecutionService $service, private readonly SecretRedactor $redactor) {}

    public function index(Request $request): JsonResponse
    {
        $executions = ActionExecution::query()->with(['website', 'component', 'requester'])->visibleTo($request->user())->latest()->paginate(20);

        return ApiResponse::success(ActionExecutionResource::collection($executions->items())->resolve($request), meta: ['pagination' => ['current_page' => $executions->currentPage(), 'total' => $executions->total()]]);
    }

    public function show(Request $request, ActionExecution $execution): JsonResponse
    {
        abort_unless(ActionExecution::query()->whereKey($execution->id)->visibleTo($request->user())->exists(), 403);

        return ApiResponse::success(['execution' => new ActionExecutionResource($execution->load(['website', 'component', 'requester']))]);
    }

    public function cancel(Request $request, ActionExecution $execution): JsonResponse
    {
        abort_unless(ActionExecution::query()->whereKey($execution->id)->visibleTo($request->user())->exists(), 403);

        return ApiResponse::success(['execution' => new ActionExecutionResource($this->service->cancel($execution, $request->user()))], 'Action cancelled.');
    }

    public function retry(Request $request, ActionExecution $execution): JsonResponse
    {
        abort_unless(ActionExecution::query()->whereKey($execution->id)->visibleTo($request->user())->exists(), 403);
        $new = $this->service->request($execution->website, $request->user(), $execution->action_key, $execution->component, ['confirmed' => true, 'retry_of' => $execution->uuid]);

        return ApiResponse::success(['execution' => new ActionExecutionResource($new)], 'Action queued.', 202);
    }

    public function output(Request $request, ActionExecution $execution): JsonResponse
    {
        abort_unless(ActionExecution::query()->whereKey($execution->id)->visibleTo($request->user())->exists(), 403);
        $content = $this->readOutput($execution);

        return ApiResponse::success(['output' => $content, 'redacted' => true]);
    }

    public function stream(Request $request, ActionExecution $execution): StreamedResponse
    {
        abort_unless(ActionExecution::query()->whereKey($execution->id)->visibleTo($request->user())->exists(), 403);

        return response()->stream(function () use ($execution): void {
            echo 'data: '.json_encode(['output' => $this->readOutput($execution), 'status' => $execution->refresh()->status->value])."\n\n";
            ob_flush();
            flush();
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
    }

    private function readOutput(ActionExecution $execution): string
    {
        if (! $execution->output_storage_path) {
            return $execution->output_preview ?? '';
        }

        $path = storage_path('app/private/'.$execution->output_storage_path);

        return is_file($path) ? $this->redactor->redact((string) file_get_contents($path)) : ($execution->output_preview ?? '');
    }
}

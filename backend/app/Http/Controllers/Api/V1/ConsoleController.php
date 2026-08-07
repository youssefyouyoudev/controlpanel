<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConsoleExecutionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ExecuteRestrictedConsoleRequest;
use App\Http\Resources\ConsoleExecutionResource;
use App\Models\ConsoleExecution;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Services\Console\RestrictedConsoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsoleController extends Controller
{
    public function __construct(private readonly RestrictedConsoleService $console) {}

    public function commands(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);
        $component = $request->integer('website_component_id') ? WebsiteComponent::query()->whereBelongsTo($website)->find($request->integer('website_component_id')) : null;

        return ApiResponse::success([
            'mode' => 'restricted_project_console',
            'banner' => 'Restricted project console - approved commands only.',
            'container_terminal' => 'Interactive container terminal is unavailable through the installed Coolify API. Use the protected Coolify terminal.',
            'commands' => $this->console->commands($component),
        ]);
    }

    public function execute(ExecuteRestrictedConsoleRequest $request, Website $website): JsonResponse
    {
        $this->authorize('create', [ConsoleExecution::class, $website]);

        $execution = $this->console->request($website, $request->user(), $request->validated());

        return ApiResponse::success(['execution' => new ConsoleExecutionResource($execution->load('component'))], 'Console command queued.', 202);
    }

    public function show(Request $request, ConsoleExecution $execution): JsonResponse
    {
        $this->authorize('view', $execution);

        return ApiResponse::success(['execution' => new ConsoleExecutionResource($execution->load('component'))]);
    }

    public function stream(Request $request, ConsoleExecution $execution): StreamedResponse
    {
        $this->authorize('view', $execution);
        $payload = ['execution' => new ConsoleExecutionResource($execution->load('component')), 'output' => $this->console->output($execution)];

        return response()->stream(function () use ($payload): void {
            echo 'data: '.json_encode($payload)."\n\n";
        }, 200, ['Content-Type' => 'text/event-stream']);
    }

    public function cancel(Request $request, ConsoleExecution $execution): JsonResponse
    {
        $this->authorize('cancel', $execution);
        abort_unless(in_array($execution->status, [ConsoleExecutionStatus::Queued, ConsoleExecutionStatus::Running], true), 422);

        $execution->update(['status' => ConsoleExecutionStatus::Cancelled, 'finished_at' => now(), 'failure_reason' => 'Cancelled by user.']);

        return ApiResponse::success(['execution' => new ConsoleExecutionResource($execution->refresh())], 'Console command cancelled.');
    }
}

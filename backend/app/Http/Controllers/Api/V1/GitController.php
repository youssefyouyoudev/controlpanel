<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\Operations\ActionExecutionService;
use App\Services\Operations\GitService;
use App\Services\Operations\OperationWorkspaceResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitController extends Controller
{
    public function __construct(
        private readonly GitService $git,
        private readonly OperationWorkspaceResolver $resolver,
        private readonly ActionExecutionService $actions,
    ) {}

    public function status(Request $request, Website $website): JsonResponse
    {
        $component = $website->components()->where('is_active', true)->first();
        $path = $this->resolver->resolve($website, $request->user(), $component);

        return ApiResponse::success(['git' => $this->git->status($path)]);
    }

    public function commits(Request $request, Website $website): JsonResponse
    {
        $component = $website->components()->where('is_active', true)->first();
        $path = $this->resolver->resolve($website, $request->user(), $component);

        return ApiResponse::success(['commits' => $this->git->commits($path)]);
    }

    public function branches(Request $request, Website $website): JsonResponse
    {
        $component = $website->components()->where('is_active', true)->first();
        $path = $this->resolver->resolve($website, $request->user(), $component);

        return ApiResponse::success(['branches' => $this->git->branches($path)]);
    }

    public function fetch(Request $request, Website $website): JsonResponse
    {
        $execution = $this->actions->request($website, $request->user(), 'git.fetch', $website->components()->where('is_active', true)->first(), ['confirmed' => true]);

        return ApiResponse::success(['execution' => $execution->uuid], 'Git fetch queued.', 202);
    }

    public function pull(Request $request, Website $website): JsonResponse
    {
        $component = $website->components()->where('is_active', true)->first();
        $this->git->assertPullSafe($this->resolver->resolve($website, $request->user(), $component));
        $execution = $this->actions->request($website, $request->user(), 'git.pull_fast_forward', $component, ['confirmed' => true]);

        return ApiResponse::success(['execution' => $execution->uuid], 'Fast-forward pull queued.', 202);
    }
}

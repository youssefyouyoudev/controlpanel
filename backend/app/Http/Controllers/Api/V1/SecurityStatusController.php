<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityConfigurationInspector;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityStatusController extends Controller
{
    public function __invoke(Request $request, SecurityConfigurationInspector $inspector): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        return ApiResponse::success(['status' => $inspector->inspect()]);
    }
}

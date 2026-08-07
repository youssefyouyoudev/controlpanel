<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'unavailable';
        }

        return ApiResponse::success([
            'application' => config('app.name'),
            'status' => 'ok',
            'database' => $database,
            'timestamp' => now()->toISOString(),
        ]);
    }
}

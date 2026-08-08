<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Databases\DatabaseWorkbenchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseWorkbenchController extends Controller
{
    public function overview(Request $request, DatabaseWorkbenchService $workbench): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        return ApiResponse::success(['overview' => $workbench->overview()]);
    }

    public function index(Request $request, DatabaseWorkbenchService $workbench): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        return ApiResponse::success(['databases' => $workbench->databases()]);
    }

    public function show(Request $request, DatabaseWorkbenchService $workbench, string $database): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        return ApiResponse::success(['database' => ['name' => $database, 'tables' => $workbench->tables($database)]]);
    }

    public function tables(Request $request, DatabaseWorkbenchService $workbench, string $database): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        return ApiResponse::success(['tables' => $workbench->tables($database)]);
    }

    public function table(Request $request, DatabaseWorkbenchService $workbench, string $database, string $table): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        return ApiResponse::success(['table' => $workbench->table($database, $table)]);
    }

    public function rows(Request $request, DatabaseWorkbenchService $workbench, string $database, string $table): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        return ApiResponse::success(['rows' => $workbench->rows($database, $table, $request->integer('page', 1), $request->integer('per_page', (int) config('youpanel.database_admin.default_row_limit', 100)))]);
    }

    public function query(Request $request, DatabaseWorkbenchService $workbench, AuditLogger $auditLogger, string $database): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);
        $data = $request->validate([
            'sql' => ['required', 'string', 'max:20000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'current_password' => ['required', 'string'],
        ]);

        $result = $workbench->query($request->user(), $database, (string) $data['sql'], (int) ($data['limit'] ?? config('youpanel.database_admin.default_row_limit', 100)), (string) $data['current_password']);
        $auditLogger->record('database.query.executed', $request->user(), null, [
            'target_type' => 'database',
            'target_identifier' => $database,
            'classification' => $result['classification']['type'] ?? 'unknown',
            'row_count' => $result['row_count'] ?? 0,
        ]);

        return ApiResponse::success(['result' => $result]);
    }
}

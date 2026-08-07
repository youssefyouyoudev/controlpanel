<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $checks = $this->checks();
        $ready = collect($checks)->every(fn (array $check): bool => $check['ok']);
        $status = $ready ? 200 : 503;

        $data = [
            'ready' => $ready,
            'checked_at' => now()->toISOString(),
        ];

        return ApiResponse::success($data, $ready ? 'Ready.' : 'Not ready.', $status);
    }

    public function detailed(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        $checks = $this->checks(includeOptional: true);
        $ready = collect($checks)->where('required', true)->every(fn (array $check): bool => $check['ok']);

        return ApiResponse::success([
            'ready' => $ready,
            'environment' => app()->environment(),
            'checks' => $checks,
            'checked_at' => now()->toISOString(),
        ], $ready ? 'Ready.' : 'Not ready.', $ready ? 200 : 503);
    }

    /**
     * @return array<string, array{ok: bool, required: bool, message: string}>
     */
    private function checks(bool $includeOptional = false): array
    {
        $checks = [
            'database' => $this->safeCheck(function (): string {
                DB::select('select 1');

                return 'Database connection succeeded.';
            }),
            'cache' => $this->safeCheck(function (): string {
                Cache::put('youpanel:readiness', 'ok', 10);

                return Cache::get('youpanel:readiness') === 'ok'
                    ? 'Cache read/write succeeded.'
                    : 'Cache read/write returned an unexpected value.';
            }),
            'storage' => [
                'ok' => is_writable(storage_path('logs')),
                'required' => true,
                'message' => is_writable(storage_path('logs')) ? 'Storage logs directory is writable.' : 'Storage logs directory is not writable.',
            ],
        ];

        if ($includeOptional) {
            $checks['queue'] = [
                'ok' => filled(config('queue.default')),
                'required' => false,
                'message' => 'Queue connection configured as '.config('queue.default').'.',
            ];
            $checks['scheduler'] = [
                'ok' => true,
                'required' => false,
                'message' => 'Scheduler cannot be proven from an HTTP request; verify cron or systemd timer in production.',
            ];
        }

        return $checks;
    }

    /**
     * @return array{ok: bool, required: bool, message: string}
     */
    private function safeCheck(callable $check): array
    {
        try {
            $message = $check();

            return ['ok' => true, 'required' => true, 'message' => $message];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'required' => true, 'message' => 'Check failed. Review application logs for details.'];
        }
    }
}

<?php

use App\Exceptions\CoolifyAuthenticationException;
use App\Exceptions\CoolifyConnectionException;
use App\Exceptions\CoolifyRateLimitException;
use App\Exceptions\CoolifyResourceNotFoundException;
use App\Exceptions\CoolifyUnsupportedCapabilityException;
use App\Exceptions\FileConflictException;
use App\Exceptions\InvalidWorkspacePathException;
use App\Exceptions\OperationBlockedException;
use App\Exceptions\WorkspacePermissionException;
use App\Http\Middleware\PortfolioDemoModeMiddleware;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Jobs\PruneBackupRetentionJob;
use App\Jobs\PruneDeploymentLogsJob;
use App\Jobs\RecoverStaleActionsJob;
use App\Jobs\RecoverStaleDeploymentsJob;
use App\Jobs\RunWebsiteHealthCheckJob;
use App\Models\WebsiteHealthCheck;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = (string) env('TRUSTED_PROXIES', env('APP_ENV') === 'local' ? '' : 'REMOTE_ADDR');

        if ($trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
                    | Request::HEADER_X_FORWARDED_PREFIX
            );
        }

        $middleware->statefulApi();
        $middleware->trimStrings(except: ['content']);
        $middleware->append(RequestIdMiddleware::class);
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->append(PortfolioDemoModeMiddleware::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new RecoverStaleActionsJob)->everyFiveMinutes();
        $schedule->job(new RecoverStaleDeploymentsJob)->everyFiveMinutes();
        $schedule->job(new PruneBackupRetentionJob)->dailyAt('03:15');
        $schedule->job(new PruneDeploymentLogsJob)->dailyAt('03:45');
        $schedule->call(function (): void {
            WebsiteHealthCheck::query()->where('is_active', true)->pluck('id')->each(fn (int $id) => RunWebsiteHealthCheckJob::dispatch($id));
        })->everyFiveMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('The given data was invalid.', $exception->status, $exception->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage() ?: 'This action is unauthorized.', 403);
            }
        });

        $exceptions->render(function (WorkspacePermissionException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 403);
            }
        });

        $exceptions->render(function (InvalidWorkspacePathException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 422);
            }
        });

        $exceptions->render(function (FileConflictException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 409, $exception->context);
            }
        });

        $exceptions->render(function (OperationBlockedException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 422, $exception->context);
            }
        });

        $exceptions->render(function (CoolifyAuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Coolify authentication failed. Check the backend API token and permissions.', 503);
            }
        });

        $exceptions->render(function (CoolifyConnectionException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 503);
            }
        });

        $exceptions->render(function (CoolifyRateLimitException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Coolify rate limit reached. Try again later.', 429, [], ['retry_after' => $exception->retryAfter]);
            }
        });

        $exceptions->render(function (CoolifyResourceNotFoundException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Coolify resource not found.', 404);
            }
        });

        $exceptions->render(function (CoolifyUnsupportedCapabilityException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($exception->getMessage(), 501);
            }
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Resource not found.', 404);
            }
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
                $message = $status >= 500 && app()->isProduction() ? 'Server error.' : ($exception->getMessage() ?: 'Server error.');

                return ApiResponse::error($message, $status);
            }
        });
    })->create();

<?php

namespace App\Providers;

use App\Contracts\CoolifyClientInterface;
use App\Contracts\FileWorkspaceInterface;
use App\Services\Coolify\CoolifyApiClient;
use App\Services\Coolify\MockCoolifyClient;
use App\Services\Databases\DatabaseDriverInterface;
use App\Services\Databases\MySqlDatabaseDriver;
use App\Services\FileWorkspaceService;
use App\Services\Metrics\LinuxServerMetricsProvider;
use App\Services\Metrics\MockServerMetricsProvider;
use App\Services\Metrics\ServerMetricsProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ServerMetricsProvider::class, function (): ServerMetricsProvider {
            $driver = config('youpanel.metrics_driver');

            return $driver === 'mock' || ($driver === 'auto' && $this->app->environment(['local', 'testing']))
                ? new MockServerMetricsProvider
                : new LinuxServerMetricsProvider;
        });
        $this->app->bind(FileWorkspaceInterface::class, FileWorkspaceService::class);
        $this->app->bind(DatabaseDriverInterface::class, MySqlDatabaseDriver::class);
        $this->app->bind(CoolifyClientInterface::class, function (): CoolifyClientInterface {
            return config('coolify.driver') === 'api'
                ? $this->app->make(CoolifyApiClient::class)
                : $this->app->make(MockCoolifyClient::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('passwords', fn (Request $request): Limit => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('files-browse', fn (Request $request): Limit => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-search', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-read', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-write', fn (Request $request): Limit => Limit::perMinute(40)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-upload', fn (Request $request): Limit => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-download', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-recursive', fn (Request $request): Limit => Limit::perMinute(15)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-restore', fn (Request $request): Limit => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('files-permanent-delete', fn (Request $request): Limit => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('operations-read', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('operations-write', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('operations-sensitive', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('terminal-gateway', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('logs-read', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('coolify-read', fn (Request $request): Limit => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('coolify-write', fn (Request $request): Limit => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('deployments-write', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('console-run', fn (Request $request): Limit => Limit::perMinute(12)->by($request->user()?->id ?: $request->ip()));

        if ($this->app->isProduction()) {
            $requiredConfiguration = [
                'APP_KEY' => config('app.key'),
                'APP_URL' => config('app.url'),
                'FRONTEND_URL' => config('app.frontend_url') ?? config('cors.allowed_origins.0'),
                'SANCTUM_STATEFUL_DOMAINS' => config('sanctum.stateful'),
            ];

            foreach ($requiredConfiguration as $name => $value) {
                if (blank($value)) {
                    throw new RuntimeException("Missing required production configuration [{$name}].");
                }
            }

            if ((bool) config('coolify.enabled') && blank(config('coolify.api_token'))) {
                throw new RuntimeException('Coolify is enabled but COOLIFY_API_TOKEN is missing.');
            }

            if (config('app.debug') !== false) {
                throw new RuntimeException('APP_DEBUG must be false in production.');
            }

            if ((bool) config('youpanel.terminal.enabled')) {
                $terminalSecret = (string) config('youpanel.terminal.gateway_secret');
                $terminalOrigins = (array) config('youpanel.terminal.allowed_origins', []);
                if (strlen($terminalSecret) < 32 || in_array(strtolower($terminalSecret), ['secret', 'password', 'changeme', 'test', 'development'], true)) {
                    throw new RuntimeException('Terminal is enabled with a missing or weak YOUPANEL_TERMINAL_GATEWAY_SECRET.');
                }

                if ($terminalOrigins === [] || collect($terminalOrigins)->contains(fn (string $origin): bool => trim($origin) === '*' || strtolower(trim($origin)) === 'null')) {
                    throw new RuntimeException('Terminal is enabled without strict YOUPANEL_TERMINAL_ALLOWED_ORIGINS.');
                }
            }

            if ((bool) config('youpanel.database_admin.enabled')) {
                if (! in_array(config('youpanel.database_admin.mode'), ['readonly', 'managed'], true)) {
                    throw new RuntimeException('YOUPANEL_DATABASE_ADMIN_MODE must be readonly or managed.');
                }

                foreach (['host', 'username', 'password'] as $databaseKey) {
                    if (blank(config('youpanel.database_admin.'.$databaseKey))) {
                        throw new RuntimeException('Database workbench is enabled but YOUPANEL_DATABASE_ADMIN_'.strtoupper($databaseKey).' is missing.');
                    }
                }
            }
        }

        foreach (['coolify.internal_url', 'coolify.public_url'] as $key) {
            $url = (string) config($key);
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException("Invalid URL configured for [{$key}].");
            }
        }
    }
}

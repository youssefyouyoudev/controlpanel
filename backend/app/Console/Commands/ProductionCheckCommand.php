<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionCheckCommand extends Command
{
    protected $signature = 'youpanel:production-check {--json : Output machine-readable JSON}';

    protected $description = 'Run read-only deployment readiness checks for YouPanel.';

    public function handle(Migrator $migrator): int
    {
        $checks = [
            $this->check('app_env', app()->isProduction(), 'critical', 'APP_ENV should be production for live deployments.'),
            $this->check('app_debug', config('app.debug') === false, 'critical', 'APP_DEBUG must be false in production.'),
            $this->check('app_key', filled(config('app.key')), 'critical', 'APP_KEY is configured.'),
            $this->check('app_url', filter_var(config('app.url'), FILTER_VALIDATE_URL) !== false, 'critical', 'APP_URL is a valid URL.'),
            $this->check('frontend_url', filter_var(config('youpanel.frontend_url'), FILTER_VALIDATE_URL) !== false, 'critical', 'FRONTEND_URL is a valid URL.'),
            $this->check('session_secure_cookie', app()->isLocal() || (bool) config('session.secure'), 'critical', 'SESSION_SECURE_COOKIE should be true outside local development.'),
            $this->check('session_same_site', strtolower((string) config('session.same_site')) === 'lax', 'warning', 'SESSION_SAME_SITE should remain lax for the two YouPanel subdomains.'),
            $this->safeCheck('database', 'critical', fn (): bool => DB::select('select 1') !== []),
            $this->safeCheck('cache', 'critical', function (): bool {
                Cache::put('youpanel:production-check', 'ok', 10);

                return Cache::get('youpanel:production-check') === 'ok';
            }),
            $this->check('storage_writable', is_writable(storage_path('logs')) && is_writable(storage_path('framework')), 'critical', 'Laravel storage directories are writable by the app user.'),
            $this->safeCheck('pending_migrations', 'critical', function () use ($migrator): bool {
                $files = $migrator->getMigrationFiles(database_path('migrations'));
                $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

                return $pending === [];
            }),
            $this->safeCheck('owner_exists', 'critical', fn (): bool => Schema::hasTable('users') && User::query()->where('role', UserRole::Owner)->exists()),
            $this->safeCheck('owner_2fa', 'warning', fn (): bool => Schema::hasTable('users') && User::query()
                ->where('role', UserRole::Owner)
                ->whereNotNull('two_factor_confirmed_at')
                ->exists()),
            $this->check('coolify_token', ! ((bool) config('coolify.enabled')) || filled(config('coolify.api_token')), 'critical', 'COOLIFY_API_TOKEN is required when Coolify API mode is enabled.'),
            $this->check('portfolio_demo_disabled', ! (bool) config('youpanel.portfolio_demo') || ! app()->isProduction(), 'critical', 'YOUPANEL_PORTFOLIO_DEMO must not be enabled on the real production control panel.'),
        ];

        if ($this->option('json')) {
            $this->line(json_encode(['checks' => $checks, 'checked_at' => now()->toISOString()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $check) {
                $this->{$check['ok'] ? 'info' : ($check['severity'] === 'critical' ? 'error' : 'warn')}(
                    sprintf('[%s] %s: %s', $check['ok'] ? 'OK' : strtoupper($check['severity']), $check['name'], $check['message'])
                );
            }
        }

        return collect($checks)->contains(fn (array $check): bool => $check['severity'] === 'critical' && ! $check['ok'])
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return array{name: string, ok: bool, severity: string, message: string}
     */
    private function safeCheck(string $name, string $severity, callable $callback): array
    {
        try {
            $ok = (bool) $callback();

            return $this->check($name, $ok, $severity, $ok ? $name.' check passed.' : $name.' check failed.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->check($name, false, $severity, $name.' check failed. Review application logs for details.');
        }
    }

    /**
     * @return array{name: string, ok: bool, severity: string, message: string}
     */
    private function check(string $name, bool $ok, string $severity, string $message): array
    {
        return compact('name', 'ok', 'severity', 'message');
    }
}

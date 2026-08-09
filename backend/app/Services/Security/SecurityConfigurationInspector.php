<?php

namespace App\Services\Security;

use App\Services\Databases\DatabaseDriverInterface;
use Throwable;

class SecurityConfigurationInspector
{
    public function __construct(private readonly DatabaseDriverInterface $databases) {}

    /**
     * @return array{checks: array<int, array{name: string, status: string, message: string}>, score: array{passed: int, warnings: int, failed: int}}
     */
    public function inspect(): array
    {
        $checks = [
            $this->check('APP_DEBUG', config('app.debug') === false || ! app()->isProduction(), 'APP_DEBUG is false or non-production mode is active.', 'APP_DEBUG is enabled in production.'),
            $this->check('Secure session cookie', ! app()->isProduction() || (bool) config('session.secure'), 'Secure session cookies are enabled or non-production mode is active.', 'SESSION_SECURE_COOKIE should be true in production.'),
            $this->check('HTTP-only session cookie', (bool) config('session.http_only'), 'Session cookies are HTTP-only.', 'SESSION_HTTP_ONLY should be true.'),
            $this->check('Session SameSite', strtolower((string) config('session.same_site')) === 'lax', 'Session SameSite is lax.', 'SESSION_SAME_SITE should remain lax unless the deployment is redesigned.'),
            $this->check('Session encryption', (bool) config('session.encrypt') || ! app()->isProduction(), 'Session encryption is enabled or non-production mode is active.', 'SESSION_ENCRYPT is not enabled.'),
            $this->check('Terminal disabled by default', ! (bool) config('youpanel.terminal.enabled'), 'Browser terminal is disabled.', 'Browser terminal is enabled; restrict it to owner-only emergency use.', 'warning'),
            $this->check('Terminal gateway secret', ! (bool) config('youpanel.terminal.enabled') || $this->strongSecret((string) config('youpanel.terminal.gateway_secret')), 'Terminal gateway secret posture is acceptable.', 'Terminal is enabled with a missing or weak gateway secret.'),
            $this->check('Terminal origins', ! (bool) config('youpanel.terminal.enabled') || $this->strictOrigins((array) config('youpanel.terminal.allowed_origins', [])), 'Terminal allowed origins are strict.', 'Terminal is enabled without strict allowed origins.'),
            $this->check('Database workbench disabled by default', ! (bool) config('youpanel.database_admin.enabled'), 'Database workbench is disabled.', 'Database workbench is enabled; verify dedicated least-privilege grants.', 'warning'),
            $this->check('Database workbench mode', in_array(config('youpanel.database_admin.mode'), ['readonly', 'managed'], true), 'Database workbench mode is valid.', 'YOUPANEL_DATABASE_ADMIN_MODE must be readonly or managed.'),
            $this->check('Database dedicated credentials', ! (bool) config('youpanel.database_admin.enabled') || (filled(config('youpanel.database_admin.username')) && filled(config('youpanel.database_admin.password'))), 'Database workbench credentials are explicitly configured when needed.', 'Database workbench is enabled without dedicated credentials.'),
            $this->check('Discovery internal HTTP', ! (bool) config('youpanel.discovery.allow_internal_http'), 'Discovery internal HTTP probing is disabled.', 'Discovery internal HTTP probing is enabled.', 'warning'),
            $this->check('HTTPS app URL', ! app()->isProduction() || str_starts_with((string) config('app.url'), 'https://'), 'APP_URL is HTTPS or non-production mode is active.', 'APP_URL should be HTTPS in production.'),
            $this->check('HTTPS frontend URL', ! app()->isProduction() || str_starts_with((string) config('youpanel.frontend_url'), 'https://'), 'FRONTEND_URL is HTTPS or non-production mode is active.', 'FRONTEND_URL should be HTTPS in production.'),
            $this->check('Audit redaction', class_exists(\App\Services\Operations\SecretRedactor::class), 'Central audit redaction service is available.', 'Central audit redaction service is unavailable.'),
        ];

        $checks = [...$checks, ...$this->databaseGrantChecks()];

        return [
            'checks' => $checks,
            'score' => [
                'passed' => collect($checks)->where('status', 'pass')->count(),
                'warnings' => collect($checks)->where('status', 'warning')->count(),
                'failed' => collect($checks)->where('status', 'danger')->count(),
            ],
        ];
    }

    private function check(string $name, bool $ok, string $passMessage, string $failMessage, string $failStatus = 'danger'): array
    {
        return ['name' => $name, 'status' => $ok ? 'pass' : $failStatus, 'message' => $ok ? $passMessage : $failMessage];
    }

    /**
     * @return array<int, array{name: string, status: string, message: string}>
     */
    private function databaseGrantChecks(): array
    {
        if (! (bool) config('youpanel.database_admin.enabled')) {
            return [];
        }

        try {
            $diagnostics = $this->databases->securityDiagnostics();
        } catch (Throwable) {
            return [['name' => 'Database grants', 'status' => 'warning', 'message' => 'Database grants could not be inspected.']];
        }

        return [
            $this->check('Database dangerous grants', ($diagnostics['dangerous_privileges'] ?? []) === [], 'No dangerous database grants detected.', 'Dangerous database grants detected: '.implode(', ', $diagnostics['dangerous_privileges'] ?? [])),
            $this->check('Database elevated grants', ($diagnostics['elevated_privileges'] ?? []) === [], 'No elevated database grants detected.', 'Elevated database grants detected: '.implode(', ', $diagnostics['elevated_privileges'] ?? []), 'warning'),
        ];
    }

    private function strongSecret(string $secret): bool
    {
        return strlen($secret) >= 32 && ! in_array(strtolower($secret), ['secret', 'password', 'changeme', 'test', 'development'], true);
    }

    private function strictOrigins(array $origins): bool
    {
        if ($origins === []) {
            return false;
        }

        foreach ($origins as $origin) {
            if (! is_string($origin) || trim($origin) === '*' || strtolower(trim($origin)) === 'null' || filter_var($origin, FILTER_VALIDATE_URL) === false) {
                return false;
            }
        }

        return true;
    }
}

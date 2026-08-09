# YouPanel Security Hardening Implementation

Date: 2026-08-08

Scope: production security hardening implemented in the repository only. No production deployment was performed and no secrets were read or exposed.

## 1. Vulnerabilities Addressed

- Removed terminal credentials from the WebSocket URL.
- Replaced reusable terminal tokens with one-time tickets consumed atomically by Laravel.
- Prevented PTY shells from inheriting the terminal gateway process environment.
- Added gateway authentication timeout, strict origin normalization, max WebSocket payload/input/output limits, resize bounds, heartbeat cleanup, and lifecycle audit notifications.
- Disabled browser terminal and database workbench by default.
- Required explicit database workbench credentials instead of silently falling back to the app DB account.
- Added readonly database mode defaults, hardened SQL classification, query/response caps, and dangerous-grant diagnostics.
- Centralized SSRF URL validation and redirect-hop validation for server-side probes.
- Added recursive secret redaction to audit metadata.
- Removed the 2FA QR `dangerouslySetInnerHTML` sink.
- Added owner-only security status API/page and `php artisan youpanel:security-check`.
- Invalidated other database-backed sessions after password changes.

## 2. Files Changed

- Backend terminal: `backend/app/Services/Terminal/TerminalSessionService.php`, `backend/app/Http/Controllers/Api/V1/TerminalSessionController.php`, `backend/terminal-gateway/server.mjs`
- Backend database: `backend/app/Services/Databases/*`, `backend/app/Http/Controllers/Api/V1/SecurityStatusController.php`
- Backend security services: `backend/app/Services/Security/SafeUrlService.php`, `backend/app/Services/Security/SecurityConfigurationInspector.php`
- Backend audit/auth/config/routes: `backend/app/Services/AuditLogger.php`, `backend/app/Services/Operations/SecretRedactor.php`, `backend/app/Http/Controllers/Api/V1/AuthController.php`, `backend/config/youpanel.php`, `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`
- Frontend: `frontend/components/terminal-client.tsx`, `frontend/lib/api.ts`, `frontend/lib/schemas.ts`, `frontend/app/(app)/databases/page.tsx`, `frontend/app/(app)/settings/security/page.tsx`, `frontend/app/(app)/settings/security/status/page.tsx`
- Tests: terminal, database, SSRF, redaction, security status, frontend security regressions

## 3. Migrations Added

- `backend/database/migrations/2026_08_08_190000_add_consumed_at_to_terminal_sessions_table.php`

This adds nullable `terminal_sessions.consumed_at` and preserves existing rows.

## 4. Terminal Protocol Changes

Old flow:

- Browser opened `/terminal?session=...&token=...`.
- Gateway validated the query parameters and spawned PTY.

New flow:

- Browser creates a terminal ticket through Laravel after owner password confirmation.
- Browser opens the bare configured WebSocket URL.
- Browser immediately sends `{ "type": "authenticate", "session": "...", "ticket": "..." }`.
- Gateway does not spawn PTY until Laravel atomically consumes the ticket.
- Reusing the same ticket fails and records `terminal.gateway.replay`.

Deployment ordering note: deploy the Laravel API migration/code, frontend code, and terminal gateway code together. Old frontend clients will not authenticate to the new gateway because URL query credentials are rejected.

## 5. DB Security Changes

- `YOUPANEL_DATABASE_ADMIN_ENABLED` now defaults to `false`.
- `YOUPANEL_DATABASE_ADMIN_MODE` defaults to `readonly`.
- Workbench credentials now come only from `YOUPANEL_DATABASE_ADMIN_*`.
- SQL classifier blocks executable comments, multi-statements, delimiter tricks, `LOAD_FILE`, `INTO OUTFILE`, `INTO DUMPFILE`, `LOAD DATA LOCAL INFILE`, plugin/component administration, shutdown, user/grant administration, and readonly-mode mutation keywords.
- Driver enforces max query bytes, bounded rows, max response bytes, and safe identifier validation.
- `securityDiagnostics()` safely inspects grants and returns privilege names only, never credentials or raw grants.
- The database page displays non-secret warnings for dangerous/elevated grants.

## 6. SSRF Changes

- Added `SafeUrlService`.
- Configured health checks and discovery health probes now share centralized URL validation.
- Private, local, reserved, metadata, localhost, IPv4-mapped IPv6, and redirect-to-private targets are blocked unless internal probing is explicitly allowed.
- Added `YOUPANEL_DISCOVERY_ALLOW_INTERNAL_HTTP=false` default.
- Discovery blocked probes record `discovery.blocked_ssrf`.

## 7. Session/Auth Changes

- Password changes now delete other database-backed sessions for the same user.
- Added structured audit aliases for `auth.login.success`, `auth.login.failed`, `auth.2fa.success`, and `auth.2fa.failed` while preserving existing audit event names.
- Production startup checks reject unsafe terminal/database configuration.

## 8. Secret Handling Changes

- `AuditLogger` now recursively redacts metadata.
- `SecretRedactor` handles nested arrays/objects, authorization headers, bearer/basic credentials, cookies, set-cookie, token/secret/api-key key names, private-key blocks, and credential-bearing URLs.
- Terminal gateway no longer passes `process.env` to PTY.

## 9. Audit Improvements

New/strengthened events include:

- `terminal.gateway.accepted`
- `terminal.gateway.rejected`
- `terminal.gateway.replay`
- `terminal.session.disconnected`
- `terminal.session.idle_timeout`
- `terminal.session.max_duration`
- `terminal.session.output_limit`
- `discovery.blocked_ssrf`
- structured auth success/failure aliases

No passwords, TOTP codes, terminal tickets, session cookies, gateway secrets, DB passwords, authorization headers, or private keys are intentionally logged.

## 10. Tests Added

- Terminal one-time ticket, replay rejection, expired ticket rejection, wrong-owner rejection, lifecycle events, static gateway hardening checks.
- Database fail-closed defaults and dangerous SQL classifier regressions.
- SSRF private/metadata/IPv4-mapped/redirect regression tests.
- Recursive secret redaction tests.
- Owner-only security status tests.
- Frontend tests for no terminal URL credentials and no 2FA `dangerouslySetInnerHTML`.

## 11. Test Results

Latest completed checks:

- `php artisan test`: passed, 89 passed, 2 skipped on Windows platform constraints.
- `npm test`: passed, 26 passed.
- `npm run lint`: passed.
- `npm run typecheck`: passed after `next build` refreshed generated route types.
- `npm run build`: passed.
- `composer audit --no-interaction`: no advisories.
- `npm audit`: found 0 vulnerabilities.
- `node --check terminal-gateway/server.mjs`: passed.
- `php artisan youpanel:security-check`: passed in current non-production environment.

## 12. Remaining Risks

- Browser terminal remains equivalent to shell access for owners when enabled. Keep disabled unless needed.
- Database workbench safety still ultimately depends on MySQL grants. Use a dedicated least-privilege readonly user.
- Passkeys/WebAuthn are not implemented yet.
- Terminal command-level auditing is not implemented; lifecycle auditing is strengthened.
- Production OS user/group isolation is deployment work, documented separately.

## 13. Production Env Changes

Set explicitly in production:

```env
YOUPANEL_TERMINAL_ENABLED=false
YOUPANEL_DATABASE_ADMIN_ENABLED=false
YOUPANEL_DATABASE_ADMIN_MODE=readonly
YOUPANEL_DISCOVERY_ALLOW_INTERNAL_HTTP=false
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

If enabling terminal:

```env
YOUPANEL_TERMINAL_ENABLED=true
YOUPANEL_TERMINAL_WS_URL=wss://control.example.com/terminal
YOUPANEL_TERMINAL_ALLOWED_ORIGINS=https://control.example.com
YOUPANEL_TERMINAL_GATEWAY_SECRET=[REDACTED]
```

If enabling database workbench:

```env
YOUPANEL_DATABASE_ADMIN_ENABLED=true
YOUPANEL_DATABASE_ADMIN_MODE=readonly
YOUPANEL_DATABASE_ADMIN_HOST=127.0.0.1
YOUPANEL_DATABASE_ADMIN_PORT=3306
YOUPANEL_DATABASE_ADMIN_USERNAME=youpanel_readonly
YOUPANEL_DATABASE_ADMIN_PASSWORD=[REDACTED]
```

## 14. Exact Deployment Commands

Safe order:

1. Backup application DB and current release.
2. `git pull --ff-only`
3. `cd backend && composer install --no-dev --optimize-autoloader --no-interaction`
4. `cd ../frontend && npm ci`
5. `cd ../backend && php artisan migrate --force`
6. `php artisan config:cache`
7. `cd ../frontend && npm run build`
8. Restart PHP-FPM / Laravel workers.
9. Restart Next.js / PM2 frontend process.
10. Restart terminal gateway if terminal is enabled.
11. `nginx -t && systemctl reload nginx` if proxy config changed.
12. `cd backend && php artisan youpanel:security-check`
13. Smoke test login, dashboard, websites, files, database page disabled/enabled state, and terminal only if enabled.

## 15. Rollback Procedure

If the new release fails before migration:

1. Stop rollout.
2. Restore previous code release.
3. Rebuild/restart previous frontend/backend/gateway processes.

If the migration has run:

1. Disable terminal and DB workbench in env.
2. Restore previous code release.
3. If required, run `php artisan migrate:rollback --path=database/migrations/2026_08_08_190000_add_consumed_at_to_terminal_sessions_table.php`.
4. Clear/cache config and restart services.
5. Verify login and dashboard.

Protocol rollback warning: old terminal gateway/frontend uses query-string tokens. Prefer rolling forward with the hardened protocol rather than restoring that behavior.

## 16. Updated Realistic Security Score

Estimated repository posture after this implementation:

- Authentication: 9.0 / 10
- Authorization/RBAC: 9.0 / 10
- Terminal security: 8.8 / 10
- Database security: 8.8 / 10
- Filesystem: 9.0 / 10
- HTTP/CSP: 9.0 / 10
- SSRF: 9.0 / 10
- Secrets: 9.0 / 10
- Audit logging: 8.8 / 10
- Production hardening: 9.0 / 10

Overall: 8.9 / 10.

This is not a claim of absolute security. The remaining gap is mostly operational: OS isolation, database grants, terminal deployment posture, and future passkey/WebAuthn support.

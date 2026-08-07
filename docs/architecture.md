# Architecture

YouPanel is a two-app monorepo:

- `backend`: Laravel 12 REST API, Sanctum cookie sessions, MySQL-ready migrations, policies, resources, services, Pest tests.
- `frontend`: Next.js 16 App Router cockpit, TypeScript, Tailwind CSS variables, TanStack Query, React Hook Form, Zod, Axios, Lucide, Motion.

Laravel owns all security decisions. The frontend hides navigation for ergonomics, but policies enforce website isolation and role capabilities server-side.

Phase 1 services are read-only:

- `ServerMetricsProvider` exposes a contract with Linux and mock implementations.
- `ServiceStatusService` returns only allowlisted logical services.
- `AuditLogger` appends sanitized audit records without passwords, tokens, cookies, secret headers, full request bodies, or environment variables.

Phase 2 adds a file workspace bounded by database-approved roots:

- `AllowedPath` records define the exact directory a website can expose and its per-operation capabilities.
- `SecurePathResolver` canonicalizes and authorizes every path before file access. It rejects traversal, null bytes, absolute user paths, escaping symlinks, protected names and special file types.
- `FileWorkspaceService` handles listings, reads, optimistic-concurrency saves, creates, uploads, moves, copies, renames and trash moves.
- `FileRevisionService` stores bounded private snapshots for editable files.
- `TrashService` moves deleted files into `storage/app/private/trash` for retention-based recovery.
- `ArchiveService` creates bounded zip downloads and validates zip entries before extraction.

The Next.js file workspace uses TanStack Query for server state, a typed Axios client, Zod response validation and a client-only Monaco editor. Frontend role checks shape the interface, but Laravel remains the authority for every permission.

Phase 3 adds a safe operations layer:

- `WebsiteComponent` records define Laravel, Next.js, PM2 and other logical pieces inside approved roots.
- `config/youpanel-actions.php` is the version-controlled action catalog. The browser never submits commands.
- `ActionExecutionService` validates the catalog action, website membership, component containment, confirmation requirements and locks before queueing `ExecuteActionJob`.
- Executors use mock mode locally/testing and Symfony Process with server-side command arrays when explicitly configured.
- Git, logs, health checks and backups each use dedicated services with allowlisted paths, SSRF checks, redaction and private storage.
- Database notifications surface action, backup and health outcomes in the app.

Phase 4 adds Coolify and deployment orchestration:

- `CoolifyClientInterface` has mock and HTTP implementations. The token stays in Laravel configuration and never reaches Next.js.
- `CoolifyResourceLink` maps YouPanel websites/components to verified Coolify resources by opaque UUID.
- `DeploymentService` owns preflight checks, approval records, queued deployment execution, log capture, notifications and audit records.
- Resource actions resolve stored links server-side. The browser never submits arbitrary container IDs or Coolify UUIDs for controls.
- `RestrictedConsoleService` maps aliases to fixed command arrays and rejects arbitrary shell input.
- Host terminal, Docker socket access, native rollback and container terminals are explicitly unavailable in Phase 4.

Phase 5 adds final local hardening and production-preparation artifacts:

- `TwoFactorAuthenticationService` owns TOTP secret generation, QR SVG creation, code verification and recovery-code hashing.
- `AuthController` performs the password step and stores a pending 2FA user ID in the Sanctum browser session; `TwoFactorAuthenticationController` owns enrollment and recovery-code management.
- `SecurityHeadersMiddleware` sets API-only security headers; `frontend/proxy.ts` sets browser-facing CSP/security headers with per-request nonces for Next.js document routes.
- `PortfolioDemoModeMiddleware` blocks unsafe API mutations only when `YOUPANEL_PORTFOLIO_DEMO=true`.
- `ReadinessController` exposes public readiness and owner-only detailed readiness without leaking exception details.
- `youpanel:production-check` and `youpanel:permission-audit` are read-only operator commands for deployment preflight and filesystem review.
- Production deployment remains a later phase; Phase 5 creates docs and templates only.

## Frontend Request Flow

Next.js 16 uses `frontend/proxy.ts` for request-time browser security and coarse route handling. The proxy generates a nonce, attaches it to `x-nonce`, mirrors it into the request and response CSP, and redirects protected document routes to `/login?returnTo=...` only when no Laravel session cookie is present.

The cookie shortcut is not authentication. The React auth provider still calls `GET /api/v1/auth/user`, and Laravel Sanctum plus Laravel policies remain the source of truth for all private data and authorization.

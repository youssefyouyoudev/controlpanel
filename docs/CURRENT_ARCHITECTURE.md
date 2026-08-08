# YouPanel Current Architecture

Last audited: 2026-08-08

## Stack

YouPanel is a split Laravel and Next.js application.

- Backend: Laravel 12, PHP 8.2+, Sanctum, Pest, queues, scheduled jobs, Eloquent resources, and versioned JSON routes under `/api/v1`.
- Frontend: Next.js 16 App Router, React 19, TypeScript, Tailwind CSS 4, TanStack Query, Axios, Zod response validation, and small local UI primitives.
- Repository layout: `backend/` contains the API, domain services, jobs, policies, models, migrations, and tests. `frontend/` contains authenticated app routes, auth routes, the public page, shared API client, schemas, and components.

## Frontend Architecture

The frontend uses App Router route groups:

- `/` currently renders `frontend/app/page.tsx`.
- Auth pages live in `frontend/app/(auth)`.
- Authenticated product pages live in `frontend/app/(app)`, including dashboard, websites, deployments, actions, containers, backups, server, activity, settings, and website detail subpages.

Client-side data access is centralized in `frontend/lib/api.ts`. API responses are parsed with Zod schemas in `frontend/lib/schemas.ts`, so backend payload changes must be mirrored there. Authentication state is held by `frontend/components/auth-provider.tsx`; the app shell/navigation is in `frontend/components/app-shell.tsx`.

## Backend Architecture

The backend exposes public health/readiness routes and authenticated Sanctum routes in `backend/routes/api.php`. Controllers are grouped under `App\Http\Controllers\Api\V1` and return a consistent `ApiResponse` envelope.

Domain behavior is mostly service-based:

- server metrics: `App\Services\Metrics\ServerMetricsProvider`, `LinuxServerMetricsProvider`, `MockServerMetricsProvider`
- service status: `App\Services\ServiceStatusService`
- Coolify: `App\Services\Coolify\*`
- file workspace: `FileWorkspaceService`, `SecurePathResolver`, `FileRevisionService`, `TrashService`
- operations: `ActionExecutionService`, action catalog, process executor, git, health checks, backups, log reader
- restricted console: `RestrictedConsoleService`
- security/audit: `TwoFactorAuthenticationService`, `AuditLogger`, security middleware, policies

Laravel scheduling in `backend/bootstrap/app.php` recovers stale actions/deployments, prunes backup/deployment logs, and dispatches website health checks.

## Authentication

Authentication uses Laravel Sanctum stateful SPA sessions with CSRF protection. Login, logout, current user, profile, password reset, password update, and two-factor flows are exposed through `/api/v1/auth/*`.

Two-factor authentication is implemented with `pragmarx/google2fa` and encrypted/hashed recovery-code handling through `TwoFactorAuthenticationService`.

## Authorization

The role model is centralized in `App\Enums\UserRole`. Policies cover websites, servers, users, audit logs, deployments, Coolify links, console executions, backups, action executions, components, and log sources.

Website visibility is enforced by `Website::scopeVisibleTo()`: owners see all websites; other users only see websites where they are members. Mutating filesystem/operation actions require owner/developer/editor-style roles depending on policy and service checks.

## Database Schema

The database includes:

- users and Sanctum personal access tokens
- servers
- websites and website members
- audit logs and notifications
- allowed file roots, file revisions, trash entries
- website components, action assignments, action executions
- backups, backup schedules, backup profiles
- website log sources and health checks/results
- Coolify resource links and sync runs
- deployments, deployment approvals, deployment policies
- console executions
- queue/cache tables

Website records already support root path, framework, domain, repository URL/branch, status, Coolify UUID, assigned port, and metadata. Sensitive fields such as root paths and metadata are hidden from normal model serialization and selectively exposed through resources.

## API Architecture

The API is versioned under `/api/v1`, protected with `auth:sanctum` and named throttles. Feature-specific throttles exist for login, files, operations, logs, Coolify, deployments, and console commands. API resources normalize model output.

Dashboard endpoints aggregate metrics, services, websites, activity, Coolify state, and deployments:

- `GET /api/v1/dashboard/summary`
- `GET /api/v1/dashboard/metrics`
- `GET /api/v1/dashboard/services`
- `GET /api/v1/dashboard/websites`
- `GET /api/v1/dashboard/activity`

## Realtime And Streaming

The application currently uses streamed HTTP responses for deployment logs, action output, console execution output, and log tails. There is no true WebSocket/PTTY host terminal yet. The existing console is intentionally restricted to configured command aliases and does not accept raw browser-supplied shell commands.

## Server Integrations

Before this audit, server metrics were partially real on Linux and mocked in local/test environments. Metrics came from `/proc/stat`, `/proc/meminfo`, `/proc/loadavg`, `/proc/uptime`, `/proc/net/dev`, and PHP disk functions.

Service status existed as an allowlisted service abstraction, but production mode returned `unavailable` without probing system services.

## Coolify Integration

Coolify support is implemented behind `CoolifyClientInterface`, with `CoolifyApiClient` and `MockCoolifyClient`. The API client supports status/version/health checks, resource listing, resource details, application deployments, deployment cancellation/logs, and application start/stop/restart where supported by the installed Coolify API.

`CoolifySynchronizationService` updates stored links and records unmatched resources. Coolify tokens remain backend-only and are not serialized to the frontend.

## File Management

File browsing and editing are scoped to per-website `AllowedPath` records. `SecurePathResolver` canonicalizes paths, rejects traversal, rejects absolute paths, validates symlink boundaries, enforces operation permissions, and blocks protected secret-like files. `FileWorkspaceService` supports listing, reading, saving with checksum conflict checks, creating, uploading, renaming, copying, moving, trashing, searching, archives, extraction, revisions, and audit events.

## Deployment And Jobs

Deployments are model-backed and can be requested against Coolify resource links. The deployment system includes approval records, protected field validation, cancellation/redeploy endpoints, logs, stale deployment recovery, and pruning. It does not currently implement a full generic non-Coolify deploy pipeline with pull, install, build, migrate, restart, health check, and rollback for arbitrary discovered websites.

## Logs

Website log sources are model-backed and read through `LogReaderService` with redaction. Deployment/action/console log streams are exposed as streamed responses. There is no single global logs cockpit yet, but the primitives are present.

## Security Implementation

Implemented controls include Sanctum session auth, CSRF protection, role/policy authorization, route throttles, security headers, trusted proxy configuration, portfolio demo read-only middleware, audit logging, secret redaction for command/log output, two-factor authentication, protected file patterns, and path traversal/symlink boundary checks.

## Main Gaps

- No automatic Nginx website discovery/synchronization yet.
- No true browser PTY terminal or WebSocket terminal token system yet.
- No global log viewer page yet.
- Containers page is not backed by direct Docker inventory.
- Service status probing was incomplete before this audit.
- Metrics did not include architecture, CPU model/core count, memory availability, swap, filesystem list, or per-interface network data.
- Public landing page and README/docs need a later portfolio-quality pass.
- Full local browser verification and production deployment instructions still need to be completed after more implementation phases.

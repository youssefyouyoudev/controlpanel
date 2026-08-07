# Security Model

- No public registration exists.
- First owner creation uses `php artisan youpanel:create-owner`.
- Browser authentication uses Laravel Sanctum session cookies, not localStorage tokens.
- Login regenerates the session when a session is present.
- Logout invalidates the session when a session is present.
- Inactive users are rejected.
- Login and password-reset endpoints are rate limited.
- Two-factor authentication uses TOTP, QR enrollment and recovery codes.
- 2FA secrets are encrypted at rest; recovery codes are stored as encrypted password hashes and displayed only during setup/regeneration.
- If a 2FA-enabled login arrives without a browser session, the API fails closed instead of issuing an unusable challenge.
- API security headers are applied by Laravel middleware.
- Frontend document security headers are applied by `frontend/proxy.ts` with a per-request CSP nonce.
- Production frontend `script-src` does not use `unsafe-inline`; local development keeps only the minimum Next.js dev allowances.
- Style elements require the request nonce. Style attributes are isolated under `style-src-attr` because CSP nonces do not authorize attributes and the UI uses React/Motion style attributes for legitimate presentation.
- Portfolio demo mode is read-only only when explicitly enabled and must remain disabled on the real private control panel.
- Owners can see all websites and global audit records.
- Developers, editors, and viewers see only assigned websites.
- Viewers cannot modify assigned websites.
- Server settings and user management are owner-only.
- Root paths and internal metadata are not returned to non-owner users.
- Service status checks are read-only and allowlisted.
- YouPanel performs no production Nginx, Cloudflare, Docker, Coolify, systemd, firewall, sudoers, `/etc`, or existing website changes during local development.

## File Workspace

- Owners approve exact file roots per website.
- Non-owners can browse only websites assigned to them and cannot see absolute root paths.
- Viewers cannot write, even when a root has write flags enabled.
- Root flags allow operations to be disabled independently: read, write, upload, create, rename, move, copy, delete, archive and extract.
- Dangerous roots are rejected, including `/`, `/etc`, `/root`, `/home`, `/data/coolify`, Docker internals, MySQL internals and process/device filesystems.
- User-supplied paths must be relative and cannot contain traversal, encoded traversal, null bytes or Windows drive prefixes.
- Symlinks are denied if they escape the approved root.
- Protected patterns such as `.env`, keys and credential files are blocked for non-owners; protected writes are denied.
- Permanent trash deletion is owner-only and requires password confirmation.
- ZIP extraction validates entry names, size totals, file counts and symlink metadata where PHP exposes it.

## Operations

- There is no raw command endpoint.
- Actions are looked up from `config/youpanel-actions.php`.
- The browser may submit only an action key, a component ID and structured options.
- Working directories are relative to approved roots and revalidated before execution.
- Medium/high-risk actions require confirmation; high-risk actions require typed website name and password.
- Git pull is fast-forward only and blocked when local files are dirty.
- PM2 actions use only the owner-configured process name.
- Logs are read from registered sources only and redacted before response.
- Health checks block localhost, private ranges, link-local and metadata-style targets unless a future owner-only internal allowance is deliberately configured.
- Backups and action output are stored under `storage/app/private`.
- Disabled service actions document future intent but cannot run without a privileged runner.

## Coolify And Deployments

- Coolify API access is backend-only through `CoolifyClientInterface`.
- `COOLIFY_API_TOKEN` is never returned through API responses and is not a `NEXT_PUBLIC_` variable.
- Local/test mode uses `MockCoolifyClient`.
- Owners can discover and link resources; non-owners cannot create arbitrary links.
- Resource controls accept stored YouPanel link IDs, not browser-controlled Coolify UUIDs or container IDs.
- Deployment approval fingerprints are invalidated when protected deployment details change.
- Coolify logs and console output are redacted best-effort; users are warned this cannot be perfect.
- YouPanel does not expose a host terminal or Docker socket.
- Interactive container terminal is unavailable because no scoped terminal/session API was verified in Coolify 4.1.2.

## Production Readiness

- `GET /api/ready` returns a minimal public readiness envelope.
- `GET /api/v1/system/readiness` is owner-only and includes component checks.
- `php artisan youpanel:production-check` performs read-only deployment preflight checks.
- `php artisan youpanel:permission-audit` reports filesystem permission risks without changing ownership or modes.

## Browser Routing

- `/` is a public overview page and does not expose private server data.
- `/login`, `/forgot-password`, `/reset-password` and `/unauthorized` are public routes.
- Private application routes render only after the auth provider verifies the Sanctum session through Laravel.
- `returnTo` redirects are restricted to local protected routes and fall back to `/dashboard`.
- External URLs, protocol-relative URLs, JavaScript URLs and public-route redirects are rejected.

# YouPanel Production Security Hardening

This document describes recommended production deployment posture for YouPanel. It is operational guidance, not a replacement for code controls or live host verification.

## Service Accounts

Recommended account separation:

- Nginx: `www-data` or distro default.
- Laravel/PHP-FPM: dedicated app user/group where practical, not root.
- Laravel queue workers: same app user or a dedicated worker user with identical minimal access.
- Next.js/PM2 frontend: dedicated non-root user.
- Terminal gateway: dedicated `youpanel-terminal` user.
- Coolify: keep Coolify-managed services under Coolify's own isolation model.

Do not run Laravel, Next.js, or the terminal gateway as root.

## Terminal Gateway Account

Recommended `youpanel-terminal` restrictions:

- No `NOPASSWD: ALL`.
- No Docker group membership.
- No MySQL admin group membership.
- No access to `/root`.
- No SSH private keys.
- No cloud provider credentials.
- No Coolify API token in environment.
- No Laravel `APP_KEY` or database passwords in environment.
- Read/write access only to intended managed website directories.

The gateway now passes an explicit PTY environment, but the process account still matters. Keep the gateway private behind Nginx/Cloudflare and do not expose port `8787` publicly.

## Browser Terminal Position

Hardened SSH remains the primary break-glass path.

Recommended break-glass access:

- Tailscale or another private network.
- SSH keys, preferably hardware-backed.
- No password SSH from the public internet.
- Per-user Unix accounts.
- Audited sudo policy.

YouPanel browser terminal is convenience/admin functionality. Keep it disabled by default and enable only when operationally needed.

## Database Workbench Account

Use a dedicated MySQL account for the workbench.

Recommended readonly grants:

```sql
CREATE USER 'youpanel_readonly'@'127.0.0.1' IDENTIFIED BY '[REDACTED]';
GRANT SELECT, SHOW VIEW ON `app_database`.* TO 'youpanel_readonly'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Avoid:

- MySQL `root`.
- App write account reuse.
- `ALL PRIVILEGES ON *.*`.
- `FILE`, `SUPER`, `SYSTEM_USER`, `SHUTDOWN`, `RELOAD`, `CREATE USER`, `GRANT OPTION`.
- `PROCESS` unless there is a documented need.

Run `php artisan youpanel:security-check` after enabling the workbench.

## Nginx / Reverse Proxy

Recommended ownership:

- Nginx owns TLS/proxy headers and public exposure.
- Next.js owns frontend CSP/security headers.
- Laravel owns API security headers and API error envelopes.

Avoid duplicated/conflicting headers between Nginx, Cloudflare, Next.js, and Laravel.

Recommended terminal proxy behavior:

- Proxy only `/terminal` to the gateway.
- Preserve and validate WebSocket upgrade headers.
- Do not log query strings for WebSocket paths.
- Do not expose the gateway port directly.

## Filesystem

Approved roots should point only to managed application directories such as `/var/www/site`.

Avoid roots:

- `/`
- `/etc`
- `/root`
- `/home`
- `/var/lib/docker`
- `/var/lib/mysql`
- Docker socket paths

Do not use `chmod 777`. Fix ownership and group permissions instead.

## Privileged Server Actions

Current Nginx/PHP-FPM/Cloudflared privileged service actions remain disabled until a narrow privileged runner exists.

If future privileged actions are needed:

- Use a separate root-owned helper/service.
- Accept only a small allowlisted command protocol.
- Require owner role, recent privileged auth, exact confirmation, and audit logs.
- Run config validation before reloads.

Do not solve privileged actions by running the main app or terminal as root.

## Required Production Env Posture

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
YOUPANEL_TERMINAL_ENABLED=false
YOUPANEL_DATABASE_ADMIN_ENABLED=false
YOUPANEL_DATABASE_ADMIN_MODE=readonly
YOUPANEL_DISCOVERY_ALLOW_INTERNAL_HTTP=false
```

Only set `YOUPANEL_TERMINAL_ENABLED=true` or `YOUPANEL_DATABASE_ADMIN_ENABLED=true` after the service account and database grants are reviewed.

## Verification

After deployment:

```bash
php artisan migrate --force
php artisan config:cache
php artisan youpanel:security-check
composer audit --no-interaction
npm audit
```

Smoke tests:

- Login/logout.
- 2FA challenge and recovery code flow.
- Dashboard/websites load.
- File workspace path traversal remains blocked.
- Database workbench disabled by default.
- Terminal disabled by default.
- Terminal works only when explicitly enabled and gateway restarted.

## Incident Notes

If terminal ticket exposure is suspected:

- Disable terminal.
- Restart gateway.
- Expire active terminal sessions in the database.
- Rotate gateway secret.
- Review audit logs for `terminal.gateway.*`.

If Coolify token exposure is suspected:

- Disable Coolify API integration.
- Rotate token in Coolify.
- Update backend env.
- Restart Laravel workers.

If database workbench credential exposure is suspected:

- Disable database workbench.
- Revoke the workbench MySQL user.
- Create a new least-privilege user.
- Review audit logs for database query events.

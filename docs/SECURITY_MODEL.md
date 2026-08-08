# Security Model

Last updated: 2026-08-08

YouPanel uses Laravel Sanctum stateful SPA authentication, CSRF protection, role-based authorization, policies, throttles, security headers, and audit logging.

## Website Discovery

Website discovery is owner-only. It reads Nginx configuration and safely inspects referenced application paths. It does not read `.env`, private keys, database credentials, API tokens, Cloudflare secrets, or Git credentials.

Git remotes are redacted before returning to the API.

Sync creates database records but does not delete manually configured websites.

## Terminal

Terminal session creation requires:

- authenticated user
- Owner role
- current password confirmation
- short-lived token
- session ownership validation
- concurrent session limit
- audit logs

Website terminal sessions must start inside an approved active file root. Tokens are stored hashed and returned only once.

The browser talks to a WebSocket gateway, not normal HTTP command endpoints. The gateway must run as a non-root account and must validate Origin, session UUID, token, expiry, ownership, idle timeout, and max duration before connecting a real PTY.

The gateway authenticates to Laravel with `YOUPANEL_TERMINAL_GATEWAY_SECRET`. This value must be long, random, server-side only, and never exposed to the frontend.

Never configure unrestricted passwordless sudo such as:

```text
www-data ALL=(ALL) NOPASSWD: ALL
```

## Server Agent

Direct Laravel access is acceptable for read-only metrics, Nginx discovery, service state, configured files, and logs when the web process already has safe read permissions.

If production needs privileged operations, use a restricted YouPanel agent over a Unix domain socket. The agent should expose explicit operations only and must not be reachable from the Internet.

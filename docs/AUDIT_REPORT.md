# Audit Report

Last updated: 2026-08-08

## Original Problems

- Websites showed `0` because the UI only displayed existing database records visible to the authenticated user.
- No service existed to discover websites from Nginx configuration.
- The terminal page did not exist, and there was no secure terminal session model.

## Implemented Fixes

- Added Nginx-backed website discovery.
- Added owner-only scan and sync endpoints.
- Added discovered metadata to website API resources.
- Added a professional Websites dashboard with scan, sync, refresh, search, filters, status counts, and action links.
- Added terminal session tokens with owner authorization, current-password confirmation, hashed token storage, expiry, concurrent limits, safe website working directory validation, and audit logs.
- Added global `/terminal` and website `/websites/{id}/terminal` pages with xterm.js.

## Files Changed

- Backend discovery services and controller.
- Website resource and API routes.
- Terminal session model, migration, service, controller, and routes.
- Frontend website schemas, API client, Websites page, terminal client, routes, sidebar, and website navigation.
- Documentation for discovery, terminal architecture, and security.

## Database Changes

- Added `terminal_sessions` table.
- Website discovery stores sync metadata in `websites.metadata`.
- Sync creates read-only `allowed_paths` for discovered root-based websites.
- Sync creates a `discovered-app` website component.

## Security Changes

- Discovery and sync are owner-only.
- Terminal creation requires current password confirmation.
- Terminal session tokens are stored hashed.
- Website terminal roots must be inside approved file roots.
- Terminal start and close events are audited.

## Tests Added

- Nginx discovery for multiple `server_name` values.
- Root-based and reverse-proxy discovery.
- Duplicate-safe website synchronization.
- Owner access and non-owner isolation.
- Git credential redaction.
- Terminal authorization, token expiry, concurrent limits, and working-directory validation.

## Remaining Limitations

- The WebSocket PTY gateway is implemented as `backend/terminal-gateway/server.mjs`, but it must be run as a separate production process bound to `YOUPANEL_TERMINAL_WS_URL`.
- Remote Tailscale terminal support is documented but not implemented yet.
- Discovery health checks depend on the server being able to reach discovered domains.

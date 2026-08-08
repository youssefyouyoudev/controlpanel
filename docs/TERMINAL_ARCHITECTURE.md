# Terminal Architecture

Last updated: 2026-08-08

YouPanel terminal access is designed as:

```text
Browser xterm.js
  -> secure WebSocket
  -> YouPanel terminal gateway
  -> TerminalProvider
  -> Local PTY shell
```

The Laravel implementation issues short-lived terminal session tokens and validates ownership, authorization, password confirmation, and safe working directories. The browser terminal page uses xterm.js and connects to `YOUPANEL_TERMINAL_WS_URL`.

The included gateway lives at:

```text
backend/terminal-gateway/server.mjs
```

Run it with:

```bash
cd backend
npm run terminal:gateway
```

## Local Provider

The local provider should run on the managed Linux host and spawn a real PTY using the configured non-root service account.

The WebSocket protocol is JSON:

- client input: `{ "type": "input", "data": "..." }`
- client resize: `{ "type": "resize", "cols": 120, "rows": 32 }`
- server output: `{ "type": "output", "data": "..." }`
- server error: `{ "type": "error", "message": "..." }`
- server exit: `{ "type": "exit", "code": 0 }`

The gateway validates the `session` and `token` query parameters against Laravel before spawning a shell by calling:

```text
POST /api/internal/terminal/sessions/validate
```

That internal route requires `X-YouPanel-Terminal-Gateway-Secret`.

## Security Controls

Terminal session creation requires:

- authenticated Sanctum user
- Owner role
- current password confirmation
- short-lived token
- per-user concurrent session limit
- website working directory inside an approved file root
- start/close audit logs

The token is returned once and only a SHA-256 hash is stored.

## Timeouts

Configured values:

```env
YOUPANEL_TERMINAL_TOKEN_TTL_SECONDS=60
YOUPANEL_TERMINAL_IDLE_TIMEOUT_SECONDS=600
YOUPANEL_TERMINAL_MAX_DURATION_SECONDS=3600
YOUPANEL_TERMINAL_MAX_CONCURRENT_PER_USER=2
YOUPANEL_TERMINAL_WS_URL=wss://control-api.example.com/terminal
YOUPANEL_TERMINAL_GATEWAY_SECRET=generate-a-long-random-value
YOUPANEL_TERMINAL_ALLOWED_ORIGINS=https://control.example.com
YOUPANEL_TERMINAL_API_URL=http://127.0.0.1:8000
```

The WebSocket gateway enforces Origin checks, idle timeout, max duration, resize, input forwarding, and PTY cleanup. Laravel enforces token lifetime and ownership.

## Future Tailscale Support

Remote servers should use the same browser and token flow, but swap the provider:

```text
Browser
  -> WebSocket
  -> YouPanel
  -> TailscaleSshTerminalProvider
  -> SSH over Tailscale
  -> Remote PTY
```

The provider abstraction should keep frontend behavior unchanged. Tailscale auth keys and SSH private keys must stay server-side and must never be sent to the browser.

# Cloudflare Production Notes

This document is a runbook for a later deployment phase. Phase 5 did not change Cloudflare Tunnel configuration.

## Intended Hostnames

- Frontend: `control.youssefyouyou.com`
- Laravel API: `control-api.youssefyouyou.com`
- Existing Coolify: `panel.youssefyouyou.com`

## Safe Tunnel Shape

Route each public hostname to the local service that owns it. Do not expose development ports directly to the internet.

Example target shape for later review:

```text
control.youssefyouyou.com      -> local frontend runtime
control-api.youssefyouyou.com  -> local Laravel API/Nginx upstream
panel.youssefyouyou.com        -> existing Coolify installation
```

## Required Cookie Settings

```env
APP_URL=https://control-api.youssefyouyou.com
FRONTEND_URL=https://control.youssefyouyou.com
FRONTEND_URLS=https://control.youssefyouyou.com
SANCTUM_STATEFUL_DOMAINS=control.youssefyouyou.com
SESSION_DOMAIN=.youssefyouyou.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Frontend:

```env
NEXT_PUBLIC_API_URL=https://control-api.youssefyouyou.com
NEXT_PUBLIC_SESSION_COOKIE_NAME=youpanel-session
```

The cookie name is public routing metadata, not a secret. The cookie value remains HTTP-only and owned by Laravel/Sanctum.

## Security Notes

- Do not create a public customer registration route.
- Enable HTTPS-only access.
- Keep Cloudflare Access optional but recommended for the private cockpit.
- Do not forward the Docker socket, database ports, Redis, PM2 control ports or private Laravel storage.
- Keep real Coolify API tokens only in `backend/.env`.
- Keep frontend CSP in `frontend/proxy.ts`; do not add production `script-src 'unsafe-inline'`.
- If the browser reports CORS while `curl -I https://control-api.youssefyouyou.com/...` returns Cloudflare `502`, fix the API tunnel/upstream first. Laravel cannot add CORS headers to a response Cloudflare generates before reaching Laravel.

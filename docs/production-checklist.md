# Production Checklist

Phase 5 does not deploy YouPanel. Use this checklist before a later supervised deployment.

## Preflight

- Confirm no existing website, Nginx, Cloudflare Tunnel, Coolify, Docker, systemd, firewall or `/etc` configuration will be modified by the deployment step.
- Copy `backend/.env.production.example` to a real backend `.env` on the server and fill secrets there only.
- Copy `frontend/.env.production.example` to the frontend deployment environment.
- Set `APP_ENV=production`, `APP_DEBUG=false`, secure session cookies and the final subdomain URLs.
- Keep `YOUPANEL_PORTFOLIO_DEMO=false` on the real private control panel.
- Keep `COOLIFY_DRIVER=mock` until the Coolify token and permissions have been reviewed.
- Create the first owner with `php artisan youpanel:create-owner`.
- Require the owner to enable 2FA before exposing the panel through Cloudflare.

## Verification Commands

Run from `backend/` after dependencies and `.env` are configured:

```bash
php artisan migrate --force
php artisan youpanel:production-check
php artisan youpanel:permission-audit
php artisan route:list --path=api --except-vendor
php artisan test
vendor/bin/pint --test
```

Run from `frontend/`:

```bash
npm run lint
npm run typecheck
npm run build
npm test
```

## Go/No-Go

Do not proceed if `youpanel:production-check` reports a critical failure. Warnings require an explicit owner decision and a note in the deployment log.

## First Exposure

- Expose only the frontend and Laravel API hostnames.
- Do not expose MySQL, Redis, Docker, PHP-FPM, PM2 internals or private storage.
- Confirm `/api/health` and `/api/ready` before signing in.
- Confirm response headers include `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` and a CSP.
- Sign in with the owner, enable 2FA, save recovery codes offline, then re-run `php artisan youpanel:production-check`.

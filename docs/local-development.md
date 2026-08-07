# Local Development

## Backend

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan youpanel:create-owner
php artisan serve
```

Use valid local MySQL credentials in `backend/.env`. For a safe local demo seed, set `YOUPANEL_DEMO_ENABLED=true` before `php artisan db:seed`.

When `YOUPANEL_METRICS_DRIVER=auto`, local and testing environments use mock server metrics so Windows and macOS dashboards display useful CPU, memory and disk values. Production keeps the read-only Linux metrics provider unless you explicitly set `YOUPANEL_METRICS_DRIVER=mock`.

If you already created your personal owner account, the demo seeder reuses that owner and adds the sample server, websites, file roots, components, health checks and activity records around it.

Keep backend CORS aligned with the exact frontend origin you open in the browser. The default examples allow both `http://localhost:3000` and `http://127.0.0.1:3000` through `FRONTEND_URLS`.

Demo mode creates approved file roots under `backend/storage/app/youpanel-demo`. These roots contain sample Laravel, Next.js, CSS, JSON, Markdown and SVG files so the browser workspace can be exercised without touching real projects.

Useful file workspace commands:

```bash
php artisan youpanel:prune-expired-trash --dry-run
php artisan youpanel:prune-expired-trash
php artisan youpanel:production-check
php artisan youpanel:permission-audit
```

Keep demo mode disabled outside local development.

Two-factor authentication can be enabled from `/settings/security`. Local development uses the same TOTP flow as production; scan the QR code with an authenticator app and save recovery codes somewhere outside the repository.

## Frontend

```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

Do not put private backend, database, Coolify, or Cloudflare credentials in `NEXT_PUBLIC_` variables.

Use `NEXT_PUBLIC_DEMO_MODE=true` only for a read-only portfolio showcase that is paired with backend `YOUPANEL_PORTFOLIO_DEMO=true`.

The frontend file workspace is available at `/websites/{id}/files`; owner-only root settings are at `/websites/{id}/settings/files`.

Phase 3 local operations use mock execution by default:

```env
YOUPANEL_ACTION_DRIVER=mock
COOLIFY_DRIVER=mock
COOLIFY_ENABLED=false
QUEUE_CONNECTION=database
```

Run a worker when using an asynchronous queue:

```bash
php artisan queue:work
```

The operations UI is available at `/websites/{id}/overview`, `/websites/{id}/actions`, `/websites/{id}/logs`, `/websites/{id}/git`, `/websites/{id}/backups`, `/websites/{id}/deployments`, `/websites/{id}/containers`, `/websites/{id}/console`, `/actions`, `/backups`, `/deployments`, `/containers`, and `/settings/integrations/coolify`.

Do not enable real Coolify access locally unless you intentionally want Laravel to call a configured Coolify instance. Never place the Coolify token in `frontend/.env.example` or any `NEXT_PUBLIC_` variable.

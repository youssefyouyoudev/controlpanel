# Disaster Recovery

Phase 5 provides the plan, not an automated full-server restore system.

## Restore Priorities

1. DNS and Cloudflare routing for the control panel.
2. Laravel API code, `.env`, storage and database.
3. Frontend build/runtime.
4. Queue worker and scheduler.
5. Coolify linkage and deployment records.
6. Optional file roots, backups and logs.

## Minimum Recovery Inputs

- Latest database backup.
- Latest backend and frontend source revision.
- Backend `.env` from the server secret store.
- Storage backup for `storage/app/private` if file revisions, trash or backups are needed.
- Cloudflare Tunnel and Nginx notes from the production deployment phase.

## Recovery Drill

Run quarterly on a non-production machine:

```bash
cd backend
php artisan migrate --force
php artisan youpanel:production-check
php artisan youpanel:permission-audit
php artisan test
```

Then build the frontend:

```bash
cd frontend
npm ci
npm run build
```

Record the elapsed time, missing inputs and any manual corrections.

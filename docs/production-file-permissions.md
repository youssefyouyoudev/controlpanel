# Production File Permissions

Phase 2 was implemented locally only. Do not deploy these settings blindly on the production Ubuntu server.

## Recommended Rollout

1. Create an owner account with `php artisan youpanel:create-owner`.
2. Keep `YOUPANEL_DEMO_ENABLED=false`.
3. Run migrations against the intended production database.
4. Create approved roots one project at a time from the owner UI.
5. Start with read-only capabilities, then enable write/upload/delete only for directories that the PHP-FPM user can safely manage.
6. Confirm non-owner accounts only see assigned websites and do not receive absolute paths.

## Root Selection

Prefer project-level directories such as:

- `/var/www/example.com/current`
- `/var/www/example.com/releases/current`

Avoid:

- `/`
- `/etc`
- `/root`
- `/home`
- `/data/coolify`
- Docker, MySQL, systemd, Cloudflare Tunnel and Coolify internal directories

The API rejects the highest-risk paths, but production root selection should still be deliberate.

## Scheduler

Expired trash cleanup can be scheduled after review:

```bash
php artisan youpanel:prune-expired-trash --dry-run
php artisan youpanel:prune-expired-trash
```

Do not add cron/systemd entries until production deployment planning.

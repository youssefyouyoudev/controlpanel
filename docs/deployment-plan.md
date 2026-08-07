# Deployment Plan

Phases 1-4 were not deployed.

Before production deployment:

1. Create production MySQL database and least-privilege database user.
2. Configure backend `.env` with production URLs, secure cookies, mail, and queue settings.
3. Run Laravel migrations during a planned deployment window.
4. Build the Next.js app with `NEXT_PUBLIC_API_URL=https://control-api.youssefyouyou.com`.
5. Add Nginx/Cloudflare/Coolify configuration only in a later deployment phase.
6. Create the owner with `php artisan youpanel:create-owner`.
7. Have the owner enable two-factor authentication and save recovery codes offline.
8. Run `php artisan youpanel:production-check` and resolve critical failures.
9. Run `php artisan youpanel:permission-audit` and review writable roots.
10. Review `docs/production-checklist.md`, `docs/cloudflare-production.md`, `docs/database-production.md`, and `docs/disaster-recovery.md`.
11. Review `docs/production-file-permissions.md` before enabling any write-capable file roots.
12. Start production file roots as read-only and enable write/upload/delete only after permission testing.
13. Keep `YOUPANEL_ACTION_DRIVER=mock` until the production runner plan is reviewed.
14. Keep `COOLIFY_DRIVER=mock` until a real Coolify token and permissions have been reviewed.
15. Run `php artisan queue:work` under a supervised deployment only after queue storage is configured.
16. Enable real Coolify with `COOLIFY_ENABLED=true`, `COOLIFY_DRIVER=api`, `COOLIFY_INTERNAL_URL=http://127.0.0.1:8000`, and a backend-only token.
17. Test `/settings/integrations/coolify` before linking production resources.

Postponed phases:

- Production deployment
- Interactive container terminal if Coolify later exposes a verified scoped API
- Native rollback if Coolify later exposes a verified safe rollback API
- Deployment webhooks if signature behavior is verified

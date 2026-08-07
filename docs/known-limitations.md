# Known Limitations

- Phase 5 is locally verified only; it was not deployed to production.
- No production Nginx, Cloudflare Tunnel, Docker, Coolify, systemd, firewall or `/etc` configuration was changed.
- Coolify remains mocked by default. Real token permissions still need production validation.
- Interactive host/container terminal is not implemented.
- Rollback is not implemented.
- Webhook ingestion is not implemented.
- There is no public registration and no payment/subscription surface.
- Historical metrics are not stored; dashboard metrics are current snapshots.
- Backup automation exists as a foundation, but offsite backup infrastructure is not configured.
- E2E browser automation is not configured in this repository; frontend coverage is Vitest plus build/type/lint checks.
- The current local `backend/.env` database credentials were not usable during this pass, so authenticated browser login could not be exercised against MySQL until local DB credentials are corrected.

# Backup Verification

Backups are not useful until they are restored somewhere safe.

## Application Backup Checks

- Confirm backup files live outside public web roots.
- Confirm checksums are recorded and can be recalculated.
- Confirm expired backups are pruned only by a reviewed retention job.
- Confirm restore actions require owner-level authorization and explicit confirmation.

## Database Backup Checks

For each production migration window:

1. Create a database dump.
2. Restore it into a temporary non-production database.
3. Run `php artisan migrate --force` against the restored database.
4. Run a smoke test login with a temporary local owner if needed.
5. Delete the temporary database after the verification note is saved.

## What Phase 5 Does Not Do

- It does not create offsite backups automatically.
- It does not manage server snapshots.
- It does not restore Coolify resources.
- It does not deploy backup infrastructure.

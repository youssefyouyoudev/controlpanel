# Database Production Notes

YouPanel is MySQL-ready and uses reversible Laravel migrations. Phase 5 did not touch a production database.

## Database User

Create a dedicated MySQL user with only the privileges YouPanel needs on the YouPanel database. Do not reuse the root MySQL account.

Minimum expected application privileges:

```text
SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
```

`DROP` is needed for normal Laravel rollback workflows. If the production policy forbids rollback, remove `DROP` only after accepting that migrations cannot be rolled back by the app user.

## Migration Flow

```bash
cd backend
php artisan down --secret="temporary-secret-if-needed"
php artisan migrate --force
php artisan youpanel:production-check
php artisan up
```

Use maintenance mode only when a migration is expected to change behavior users can hit during the deploy.

## Backup Before Migration

Take a database backup before every production migration. Verify that the backup can be listed and restored in a non-production database before trusting it.

# Database Workbench

Last updated: 2026-08-08

The Database Workbench is an owner-only MySQL/MariaDB inspection surface under `/databases`.

## Capabilities

- database server overview
- database list
- table list
- table schema basics
- paginated row browsing
- read-only SQL console
- website to database links from discovery-safe `.env` fields

## Security Rules

- Only owners can access `/api/v1/databases/*`.
- SQL execution requires current password confirmation.
- SQL is classified before execution.
- Only read-only statements are enabled: `select`, `with`, `show`, `describe`, `desc`, and `explain`.
- Multiple statements are blocked.
- Database credentials are read server-side from Laravel config only and are never sent to the browser.
- Query executions are audited without SQL text or passwords.

## Production Configuration

Configure a dedicated database user with the least privileges needed for inspection. For the current read-only workbench, prefer `SELECT`, `SHOW VIEW`, and metadata access only.

```env
YOUPANEL_DATABASE_ADMIN_ENABLED=true
YOUPANEL_DATABASE_ADMIN_DRIVER=mysql
YOUPANEL_DATABASE_ADMIN_HOST=127.0.0.1
YOUPANEL_DATABASE_ADMIN_PORT=3306
YOUPANEL_DATABASE_ADMIN_USERNAME=youpanel_readonly
YOUPANEL_DATABASE_ADMIN_PASSWORD=change-me
YOUPANEL_DATABASE_ADMIN_CONNECT_TIMEOUT_SECONDS=5
YOUPANEL_DATABASE_ADMIN_QUERY_TIMEOUT_SECONDS=15
YOUPANEL_DATABASE_ADMIN_DEFAULT_ROW_LIMIT=100
YOUPANEL_DATABASE_ADMIN_MAX_ROW_LIMIT=500
```

Apply the code in production with:

```bash
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan optimize
```

## Extension Path

The backend uses `DatabaseDriverInterface`, currently implemented by `MySqlDatabaseDriver`. Additional adapters can be added without changing the controller or frontend route shape.

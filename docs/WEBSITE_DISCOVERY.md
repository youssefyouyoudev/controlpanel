# Website Discovery

Last updated: 2026-08-08

YouPanel discovers websites from Nginx configuration first. Nginx is the entry point, not the whole answer: after a `server` block points at a document root or reverse proxy, YouPanel resolves the logical project root and inspects the application structure around it.

## Sources

The scanner reads files from:

- `/etc/nginx/sites-enabled`
- `/etc/nginx/sites-available`

These paths can be overridden with:

```env
YOUPANEL_NGINX_DISCOVERY_PATHS=/etc/nginx/sites-enabled,/etc/nginx/sites-available
YOUPANEL_DISCOVERY_ALLOWED_ROOTS=/var/www
```

## What Is Parsed

For each `server { ... }` block, YouPanel extracts:

- `server_name`
- aliases
- `listen` ports
- `root`
- `proxy_pass`
- `ssl_certificate`

Nginx remains the public ingress source of truth. Filesystem inspection only happens after a server block points to a safe readable path, and upward project-root resolution is bounded by `YOUPANEL_DISCOVERY_ALLOWED_ROOTS`.

## Project Root Resolution

The resolver scores the Nginx root and its parents until the allowed root boundary. This catches layouts like:

- `/var/www/erplus/backend/public` as document root
- `/var/www/erplus/backend` Laravel app
- `/var/www/erplus/frontend` React/Vite app
- `/var/www/erplus/.git` as the logical repository

The synchronized website root becomes `/var/www/erplus`, while the Nginx document root remains recorded separately.

## Stack Detection

YouPanel inspects indicator files and JSON manifests without reading secrets:

- `artisan` and `composer.json`: Laravel
- `wp-config.php`: WordPress
- `next.config.*`: Next.js
- `vite.config.*`: Vite, React, or Vue
- `package.json`: Node.js
- `Dockerfile` or `docker-compose.*`: Docker application
- `public/index.php` or `index.php`: PHP
- `index.html`: static
- `proxy_pass` without a root: reverse proxy

The API stores structured project metadata:

- `architecture`: `full-stack`, `backend`, `frontend`, `reverse-proxy`, `static`, or `unknown`
- `frameworks`: detected frameworks such as `Laravel`, `Next.js`, `React / Vite`
- `runtimes`: `PHP`, `Node`, `nginx`, Docker hints
- `components`: role, type, framework, runtime, and relative path for each app component
- `processes`: correlated PM2, PHP-FPM, and Docker hints when available
- `ssl`: origin TLS, public HTTPS inference, and proxy mode
- `databases`: safe `.env` database hints only

## Database Detection

Discovery reads only safe `.env` keys:

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`

It never returns `DB_USERNAME`, `DB_PASSWORD`, URLs, tokens, or arbitrary environment values. Sync stores detected website/database associations in `website_databases`.

## Git Metadata

If the application root contains `.git`, YouPanel reads:

- remote URL with credentials redacted
- branch
- last commit hash
- last commit message
- last commit date
- dirty/clean state

It never returns `.env` values, SSH keys, Git credentials, or tokens.

## Synchronization

`Scan server` performs read-only discovery and returns discovered resources.

`Sync websites` creates or updates database records. Matching happens by:

1. discovery stable ID
2. primary domain
3. root path

Sync never deletes manually configured websites. Root-based discovered websites receive a read-only allowed file root, and an application component is created from the detected stack.

Owners can see synchronized local websites automatically through the existing owner authorization model. Non-owner users still only see assigned websites.

## Production Checklist

Set these on the production Laravel service, then clear config cache:

```bash
YOUPANEL_NGINX_DISCOVERY_PATHS=/etc/nginx/sites-enabled,/etc/nginx/sites-available
YOUPANEL_DISCOVERY_ALLOWED_ROOTS=/var/www
YOUPANEL_DISCOVERY_HEALTH_CHECKS=true

php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan optimize
```

The web user needs read access to Nginx site files and project metadata files. Do not grant broad write access or unrestricted sudo for discovery.

# Website Discovery

Last updated: 2026-08-08

YouPanel discovers websites from Nginx configuration first. It does not treat every `/var/www` folder as a website.

## Sources

The scanner reads files from:

- `/etc/nginx/sites-enabled`
- `/etc/nginx/sites-available`

These paths can be overridden with:

```env
YOUPANEL_NGINX_DISCOVERY_PATHS=/etc/nginx/sites-enabled,/etc/nginx/sites-available
```

## What Is Parsed

For each `server { ... }` block, YouPanel extracts:

- `server_name`
- aliases
- `listen` ports
- `root`
- `proxy_pass`
- `ssl_certificate`

Nginx remains the source of truth. Filesystem inspection only happens after a server block points to a safe readable path.

## Stack Detection

YouPanel inspects indicator files without reading secrets:

- `artisan` and `composer.json`: Laravel
- `wp-config.php`: WordPress
- `next.config.*`: Next.js
- `vite.config.*`: Vite, React, or Vue
- `package.json`: Node.js
- `Dockerfile` or `docker-compose.*`: Docker application
- `public/index.php` or `index.php`: PHP
- `index.html`: static
- `proxy_pass` without a root: reverse proxy

When Nginx points at `public/`, YouPanel records the parent directory as the application root and keeps the Nginx document root separately.

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


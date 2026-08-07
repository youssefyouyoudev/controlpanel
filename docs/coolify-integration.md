# Coolify Integration

Phase 4 adds a backend-only Coolify adapter. The browser never receives `COOLIFY_API_TOKEN`; all Coolify requests pass through Laravel services.

Local development defaults to mock mode:

```env
COOLIFY_ENABLED=false
COOLIFY_DRIVER=mock
```

Real access later requires:

```env
COOLIFY_ENABLED=true
COOLIFY_DRIVER=api
COOLIFY_INTERNAL_URL=http://127.0.0.1:8000
COOLIFY_PUBLIC_URL=https://panel.youssefyouyou.com
COOLIFY_API_TOKEN=
COOLIFY_VERIFY_TLS=true
```

Use the internal URL for Laravel-to-Coolify traffic when YouPanel runs on the same server. The public URL is returned only as a safe link for owners.

Cloudflare Access can protect the public Coolify UI. YouPanel should not disable it or bypass it for users; server-to-server API traffic should use the internal URL when deployed on the same host.


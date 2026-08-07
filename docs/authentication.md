# Authentication

YouPanel uses Laravel Sanctum session-cookie authentication for the browser application. Authentication state is never stored in `localStorage`.

## Browser Flow

1. The frontend initializes CSRF through Sanctum before login mutations.
2. `POST /api/v1/auth/login` performs the password step.
3. If the account requires TOTP, Laravel stores a pending 2FA user ID in the server-side session.
4. The TOTP challenge verifies the code or recovery code before the user is considered authenticated.
5. The frontend confirms the session with `GET /api/v1/auth/user`.
6. Logout calls Laravel and invalidates the server-side session.

The Axios client uses `withCredentials: true` and `withXSRFToken: true`. Both are required because local development uses separate frontend/API origins, and production uses separate control/control-api subdomains.

## Route Protection

`frontend/proxy.ts` redirects protected document requests to login when the Laravel session cookie is absent. This is only an early user-experience shortcut.

Laravel Sanctum and Laravel policies remain authoritative. A stale, forged or expired cookie does not grant access because private pages wait for `GET /api/v1/auth/user`, and every API endpoint enforces backend authorization.

## Redirect Safety

Login uses `returnTo`. `frontend/lib/routing.ts` accepts only local protected routes and rejects external URLs, protocol-relative URLs, JavaScript URLs and public-route targets.

## Required Production Cookie Settings

```env
APP_URL=https://control-api.youssefyouyou.com
FRONTEND_URL=https://control.youssefyouyou.com
FRONTEND_URLS=https://control.youssefyouyou.com
SANCTUM_STATEFUL_DOMAINS=control.youssefyouyou.com
SESSION_DOMAIN=.youssefyouyou.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
NEXT_PUBLIC_SESSION_COOKIE_NAME=youpanel-session
```

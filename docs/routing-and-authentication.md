# Routing And Authentication

## Public Routes

- `/`
- `/login`
- `/forgot-password`
- `/reset-password`
- `/unauthorized`

## Protected Route Prefixes

- `/dashboard`
- `/websites`
- `/server`
- `/deployments`
- `/actions`
- `/backups`
- `/containers`
- `/activity`
- `/settings`

The shared route classification lives in `frontend/lib/routing.ts`.

## Root Route

`/` is a public landing page for everyone. It does not redirect to `/dashboard` and does not expose private server data.

## Protection Strategy

The Next.js proxy performs only an obvious unauthenticated shortcut: if a protected document route has no Laravel session cookie at all, it redirects to `/login?returnTo=...`.

The presence of the cookie is not treated as proof of authentication. Protected app routes still wait for the frontend auth provider to verify the session through Laravel:

```text
GET /api/v1/auth/user
```

Laravel Sanctum and Laravel policies remain the source of truth for authentication and authorization.

## Return Paths

Login accepts `returnTo`, not arbitrary external URLs. `safeReturnTo()` rejects:

- absolute external URLs
- protocol-relative URLs
- `javascript:` URLs
- public routes such as `/login`

Invalid values fall back to `/dashboard`.

## API Offline

If the API cannot be reached while a protected shell is checking the session, the app displays a stable session-verification error instead of looping between login and dashboard.

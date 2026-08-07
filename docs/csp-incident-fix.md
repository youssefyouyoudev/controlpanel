# CSP Incident Fix

## Root Cause

The frontend CSP was defined in `frontend/next.config.ts`:

```text
script-src 'self' 'unsafe-eval' blob:
```

That policy did not allow inline scripts by nonce, hash or `unsafe-inline`. Next.js App Router emits inline bootstrap scripts, including the script that initializes `self.__next_r`. Because the browser blocked those scripts, hydration failed and Next reported:

```text
Invariant: Expected a request ID to be defined for the document via self.__next_r.
```

The `self.__next_r` error was therefore secondary to the CSP violation.

## Source Of Active CSP

Before the fix, `curl -I http://127.0.0.1:3000/login` showed a single frontend CSP header from `next.config.ts`.

Laravel also emits an API-only CSP:

```text
default-src 'none'; frame-ancestors 'none'; base-uri 'none'
```

That API CSP was not the source of the frontend hydration failure.

## Fix

- Removed frontend CSP from `next.config.ts`.
- Added `frontend/proxy.ts` as the single frontend CSP authority.
- Generated a fresh nonce per document request.
- Set CSP on both the incoming request and outgoing response.
- Read `x-nonce` in `app/layout.tsx`.
- Passed the nonce to `next-themes`.
- Disabled `next-themes` inline `color-scheme` writes because YouPanel already defines `color-scheme` in CSS.
- Split `style-src-elem` from `style-src-attr` so nonced style elements and React/Motion style attributes use the correct CSP directives.
- Replaced `/` dashboard redirect with a public landing page.
- Added route classification and safe `returnTo` handling.

## Verification

Live checks confirmed:

- `/` returns `200`.
- `/dashboard` without a Laravel session cookie redirects to `/login?returnTo=%2Fdashboard`.
- CSP contains a fresh nonce per request.
- Rendered Next scripts include nonce attributes.
- No rendered login scripts are missing nonce attributes.
- Browser console has no CSP, hydration or `self.__next_r` errors in the unauthenticated flow.

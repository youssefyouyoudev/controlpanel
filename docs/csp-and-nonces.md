# CSP And Nonces

YouPanel frontend CSP is controlled in `frontend/proxy.ts`.

## Architecture

- `proxy.ts` generates a cryptographically random nonce for each document request.
- The nonce is added to the incoming request as `x-nonce`.
- The same nonce is embedded in the request and response `Content-Security-Policy`.
- The root App Router layout reads `x-nonce` with `headers()` and passes it to `next-themes`.
- Next.js parses the request CSP and applies the nonce to framework scripts and inline bootstrap scripts.

## Development CSP

Development allows `unsafe-eval` for the Next.js/React dev runtime.

`script-src` does not include `unsafe-inline`.

## Production CSP

Production removes `unsafe-eval`, keeps nonce-based scripts, and adds `upgrade-insecure-requests`.

Conceptually:

```text
script-src 'self' 'nonce-{requestNonce}' 'strict-dynamic'
style-src 'self'
style-src-elem 'self' 'unsafe-inline'
style-src-attr 'unsafe-inline'
worker-src 'self' blob:
```

Style directives are intentionally split from script directives. Scripts remain nonce-based and do not allow production `unsafe-inline`. Runtime CSS inserted by Next.js/font/UI tooling can create inline `<style>` elements that do not receive the request nonce, so `style-src-elem` allows inline styles. React/Motion style attributes are isolated under `style-src-attr`.

## Monaco

Monaco workers require `worker-src 'self' blob:`. No third-party script origin is allowed for Monaco.

## HSTS

The frontend emits HSTS only in production HTTPS requests. Local HTTP development does not receive HSTS.

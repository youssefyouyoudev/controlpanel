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
style-src-elem 'self' 'nonce-{requestNonce}'
style-src-attr 'unsafe-inline'
worker-src 'self' blob:
```

`style-src-attr 'unsafe-inline'` is intentionally split from `style-src-elem`. CSP nonces authorize `<style>` elements, not React style attributes. Motion-driven UI transitions and some browser-controlled component styling require style attributes, while injected style elements still require the request nonce.

## Monaco

Monaco workers require `worker-src 'self' blob:`. No third-party script origin is allowed for Monaco.

## HSTS

The frontend emits HSTS only in production HTTPS requests. Local HTTP development does not receive HSTS.

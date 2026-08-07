# Production Runner Design

Phase 3 does not configure privileged service control.

A future production runner should be a dedicated `youpanel-agent` with:

- fixed operation identifiers
- no raw command arguments
- local Unix socket
- authenticated or signed requests
- root-owned configuration
- strict allowlist
- structured responses
- audit integration

Do not configure broad passwordless sudo. In particular, never use:

```text
www-data ALL=(ALL) NOPASSWD: ALL
```

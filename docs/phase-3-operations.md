# Phase 3 Operations

Implemented in Phase 3:

- website components
- server-side action catalog
- queued action executions
- mock/process action adapters
- Git status and safe pull queueing
- allowlisted log viewer
- private backups and staged restore confirmation
- health checks with SSRF protection
- database notifications
- operations pages in Next.js

Known limitations:

- mock execution is the local default
- production service actions are disabled
- restore is staged, not live overwrite
- Coolify deployment integration is Phase 4
- restricted terminal is still postponed

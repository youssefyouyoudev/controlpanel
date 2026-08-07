# Coolify Capability Matrix

Verified against the official Coolify OpenAPI source for the `v4.x` branch and intended for Coolify 4.1.2. YouPanel uses only documented API paths under `/api/v1`. This local Phase 4 pass did not query the production Coolify instance or modify Coolify configuration.

| Capability | Supported by Coolify 4.1.2 | Verified endpoint | Required permission | Implemented in YouPanel | Fallback behavior |
| --- | --- | --- | --- | --- | --- |
| Health check | Yes | `GET /health` | None/API reachable | Yes | Show unreachable/degraded state |
| Version | Yes | `GET /version` | Read token | Yes | Show version unavailable |
| List applications | Yes | `GET /applications` | Read | Yes | Resource discovery omits applications |
| Get application | Yes | `GET /applications/{uuid}` | Read | Yes | Link verification fails safely |
| Application logs | Yes | `GET /applications/{uuid}/logs` | Read/log access | Yes | Log viewer shows unavailable |
| Start application | Yes | `POST /applications/{uuid}/start` | Deploy/control | Yes | Control disabled with clear error |
| Stop application | Yes | `POST /applications/{uuid}/stop` | Deploy/control | Yes | Control disabled with clear error |
| Restart application | Yes | `POST /applications/{uuid}/restart` | Deploy/control | Yes | Control disabled with clear error |
| Trigger deploy | Yes | `POST /deploy?uuid={uuid}` | Deploy | Yes | Deployment is rejected |
| List running deployments | Yes | `GET /deployments` | Read | Yes | Local records remain visible |
| Get deployment | Yes | `GET /deployments/{uuid}` | Read | Yes | Status remains unknown |
| Cancel deployment | Yes | `POST /deployments/{uuid}/cancel` | Deploy/control | Yes | Cancel unavailable |
| Application deployment history | Yes | `GET /deployments/applications/{uuid}` | Read | Yes | Only local history is shown |
| Projects | Yes | `GET /projects` | Read | Yes | Project labels unavailable |
| Servers | Yes | `GET /servers` | Read | Yes | Server labels unavailable |
| Services | Yes | `GET /services` | Read | Discovery only | Service controls unavailable |
| Databases | Yes | `GET /databases` | Read | Discovery only | Database controls unavailable |
| Generic resources | Partial/complex | `GET /resources` | Read | No | Uses typed endpoints instead |
| Container metrics | No verified route | None | Not documented | No | Resource status shown; metrics unavailable |
| Scoped container terminal | No verified route | None | Not documented | No | Protected Coolify terminal link only |
| Host terminal | Not exposed | None | Not exposed | No | No YouPanel endpoint exists |
| Rollback | No verified safe route | None | Not verified | No | Unavailable |
| Deployment webhooks | Not verified | None | Not verified | No | Poll/manual sync foundation |

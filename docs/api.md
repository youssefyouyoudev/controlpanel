# API

All API responses use a consistent envelope:

```json
{
  "ok": true,
  "message": "OK",
  "data": {},
  "meta": { "request_id": "..." },
  "errors": null
}
```

Implemented endpoints:

- `GET /api/health`
- `GET /api/ready`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/two-factor-challenge`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/user`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `PUT /api/v1/auth/profile`
- `PUT /api/v1/auth/password`
- `GET /api/v1/auth/two-factor`
- `POST /api/v1/auth/two-factor`
- `POST /api/v1/auth/two-factor/confirm`
- `POST /api/v1/auth/two-factor/recovery-codes`
- `DELETE /api/v1/auth/two-factor`
- `GET /api/v1/system/readiness`
- `GET /api/v1/dashboard/summary`
- `GET /api/v1/dashboard/metrics`
- `GET /api/v1/dashboard/services`
- `GET /api/v1/dashboard/websites`
- `GET /api/v1/dashboard/activity`
- `GET /api/v1/websites`
- `GET /api/v1/websites/{website}`
- `GET /api/v1/websites/{website}/file-roots`
- `POST /api/v1/websites/{website}/file-roots`
- `GET /api/v1/websites/{website}/file-roots/{allowedPath}`
- `PUT /api/v1/websites/{website}/file-roots/{allowedPath}`
- `DELETE /api/v1/websites/{website}/file-roots/{allowedPath}`
- `POST /api/v1/websites/{website}/file-roots/{allowedPath}/validate`
- `GET /api/v1/websites/{website}/files`
- `GET /api/v1/websites/{website}/files/metadata`
- `GET /api/v1/websites/{website}/files/search`
- `GET /api/v1/websites/{website}/files/content`
- `PUT /api/v1/websites/{website}/files/content`
- `POST /api/v1/websites/{website}/files/create`
- `POST /api/v1/websites/{website}/directories`
- `POST /api/v1/websites/{website}/files/upload`
- `GET /api/v1/websites/{website}/files/download`
- `POST /api/v1/websites/{website}/files/archive`
- `POST /api/v1/websites/{website}/files/extract`
- `POST /api/v1/websites/{website}/files/rename`
- `POST /api/v1/websites/{website}/files/move`
- `POST /api/v1/websites/{website}/files/copy`
- `DELETE /api/v1/websites/{website}/files`
- `GET /api/v1/websites/{website}/files/revisions`
- `GET /api/v1/websites/{website}/files/revisions/{revision}`
- `POST /api/v1/websites/{website}/files/revisions/{revision}/restore`
- `GET /api/v1/websites/{website}/trash`
- `POST /api/v1/websites/{website}/trash/{trashEntry}/restore`
- `DELETE /api/v1/websites/{website}/trash/{trashEntry}`
- `POST /api/v1/websites/{website}/trash/empty-expired`

File endpoints require Sanctum cookie authentication. Every file operation requires an `allowed_path_id`; the browser cannot submit arbitrary server paths. Permanent trash deletion accepts `{ "password": "..." }` and is owner-only.

Phase 3 operations endpoints:

- `GET /api/v1/websites/{website}/components`
- `POST /api/v1/websites/{website}/components`
- `GET /api/v1/websites/{website}/actions`
- `POST /api/v1/websites/{website}/actions/{actionKey}/execute`
- `GET /api/v1/action-executions`
- `GET /api/v1/action-executions/{execution}`
- `POST /api/v1/action-executions/{execution}/cancel`
- `POST /api/v1/action-executions/{execution}/retry`
- `GET /api/v1/action-executions/{execution}/output`
- `GET /api/v1/action-executions/{execution}/stream`
- `GET /api/v1/websites/{website}/git/status`
- `GET /api/v1/websites/{website}/git/commits`
- `GET /api/v1/websites/{website}/git/branches`
- `POST /api/v1/websites/{website}/git/fetch`
- `POST /api/v1/websites/{website}/git/pull`
- `GET /api/v1/websites/{website}/logs/sources`
- `GET /api/v1/websites/{website}/logs/{source}`
- `GET /api/v1/websites/{website}/logs/{source}/stream`
- `GET /api/v1/websites/{website}/backups`
- `POST /api/v1/websites/{website}/backups`
- `POST /api/v1/websites/{website}/backups/{backup}/verify`
- `POST /api/v1/websites/{website}/backups/{backup}/restore`
- `GET /api/v1/websites/{website}/health`
- `POST /api/v1/websites/{website}/health/check`
- `GET /api/v1/notifications`
- `POST /api/v1/notifications/{notification}/read`
- `POST /api/v1/notifications/read-all`

Phase 4 Coolify and deployment endpoints:

- `GET /api/v1/integrations/coolify/status`
- `POST /api/v1/integrations/coolify/test`
- `GET /api/v1/integrations/coolify/capabilities`
- `POST /api/v1/integrations/coolify/synchronize`
- `GET /api/v1/integrations/coolify/resources`
- `GET /api/v1/websites/{website}/coolify-links`
- `POST /api/v1/websites/{website}/coolify-links`
- `PUT /api/v1/websites/{website}/coolify-links/{link}`
- `DELETE /api/v1/websites/{website}/coolify-links/{link}`
- `POST /api/v1/websites/{website}/coolify-links/{link}/verify`
- `GET /api/v1/deployments`
- `GET /api/v1/deployments/{deployment}`
- `POST /api/v1/websites/{website}/deployments`
- `POST /api/v1/deployments/{deployment}/approve`
- `POST /api/v1/deployments/{deployment}/reject`
- `POST /api/v1/deployments/{deployment}/cancel`
- `POST /api/v1/deployments/{deployment}/redeploy`
- `GET /api/v1/deployments/{deployment}/logs`
- `GET /api/v1/deployments/{deployment}/stream`
- `GET /api/v1/websites/{website}/resources`
- `GET /api/v1/websites/{website}/resources/{link}`
- `POST /api/v1/websites/{website}/resources/{link}/start`
- `POST /api/v1/websites/{website}/resources/{link}/stop`
- `POST /api/v1/websites/{website}/resources/{link}/restart`
- `GET /api/v1/websites/{website}/console/commands`
- `POST /api/v1/websites/{website}/console/execute`
- `GET /api/v1/console-executions/{execution}`
- `GET /api/v1/console-executions/{execution}/stream`
- `POST /api/v1/console-executions/{execution}/cancel`

No host-terminal endpoint exists. No endpoint accepts arbitrary Docker container IDs for terminal or resource control.

Production cookie settings:

```env
APP_URL=https://control-api.youssefyouyou.com
FRONTEND_URL=https://control.youssefyouyou.com
SANCTUM_STATEFUL_DOMAINS=control.youssefyouyou.com
SESSION_DOMAIN=.youssefyouyou.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

# Coolify Troubleshooting

Common states:

- `Coolify integration is disabled`: local mock/default mode.
- `Coolify authentication failed`: backend token missing or invalid.
- `Coolify is currently unreachable`: internal URL, service availability or network issue.
- `Coolify rate limit reached`: wait for the `Retry-After` window.
- `This action is not available through the installed Coolify API`: unsupported capability.

Diagnostics live at `/settings/integrations/coolify` and are owner-only.


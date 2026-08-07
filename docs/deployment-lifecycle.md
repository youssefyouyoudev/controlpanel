# Deployment Lifecycle

1. User selects a stored Coolify resource link.
2. Laravel authorizes the website and role.
3. Laravel verifies the stored link against Coolify.
4. Deployment preflight checks run.
5. A local deployment record is created.
6. Production deployments requested by developers move to `awaiting_approval`.
7. Approved or owner-requested deployments dispatch `RunCoolifyDeploymentJob`.
8. The Coolify client calls `POST /deploy?uuid={uuid}`.
9. Deployment logs/status are normalized and redacted.
10. The deployment becomes `succeeded`, `failed`, `cancelled`, `timed_out` or `unknown`.
11. Audit and notifications are recorded.

YouPanel does not run Git pulls or database migrations automatically for Coolify deployments.


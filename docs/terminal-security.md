# Terminal Security

Phase 4 does not expose a host terminal. It also does not expose an interactive container terminal because the verified Coolify OpenAPI did not provide a scoped terminal/session API.

YouPanel never mounts or reads `/var/run/docker.sock`, never accepts arbitrary container IDs, and never runs `docker exec` from browser input.

Owners can use the protected Coolify UI for advanced terminal access where appropriate.


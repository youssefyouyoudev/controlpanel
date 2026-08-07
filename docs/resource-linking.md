# Resource Linking

Owners map YouPanel websites/components to existing Coolify resources. Links store opaque Coolify UUIDs and normalized safe metadata only.

Rules:

- Non-owners cannot create or remove links.
- Link creation verifies the Coolify resource exists.
- One Coolify resource cannot be linked to unrelated websites.
- Removing a link never deletes the Coolify resource.
- Browser resource controls submit a YouPanel link ID, not a Coolify UUID.


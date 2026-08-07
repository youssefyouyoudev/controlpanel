# Filesystem Threat Model

YouPanel treats browser file access as sensitive server administration.

| Risk | Control |
| --- | --- |
| Browsing arbitrary server paths | Browser must use owner-approved `allowed_path_id`; raw absolute request paths are rejected. |
| Root path confusion | Roots are canonicalized and dangerous system directories are blocked. |
| `../` traversal | Resolver rejects traversal before filesystem access, including encoded traversal. |
| Symlink escape | Resolver rejects symlinks that resolve outside the approved root. |
| Secret disclosure | Protected patterns block `.env`, key and credential files for non-owners; absolute paths are hidden from non-owners. |
| Binary or oversized editor crash | Editor reads are limited by size and binary detection. |
| Concurrent overwrite | Saves require the current checksum and return `409 Conflict` on stale writes. |
| Destructive delete | Delete moves items to private trash first. Permanent delete is owner-only with password confirmation. |
| Zip slip | Archive extraction validates names, sizes, file counts and symlink metadata where available. |
| Privilege escalation through commands | Phase 2 performs no `sudo`, no service control and no user-supplied shell commands. |

## Residual Risk

- PHP filesystem permission errors can still occur on real hosts; the API returns graceful errors.
- Very large repositories may require tighter limits or paged directory listing in a later phase.
- Archive symlink metadata depends on PHP/ZipArchive support; unsafe paths are still rejected.

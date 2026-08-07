# File Workspace

Phase 2 adds a browser file workspace for assigned websites.

## Backend Flow

1. Owner creates an `allowed_paths` record for a website.
2. Browser requests pass `allowed_path_id` plus a relative path.
3. `SecurePathResolver` authorizes the website, checks root capabilities, canonicalizes the path and rejects unsafe targets.
4. `FileWorkspaceService` performs the read-only or write operation.
5. Writes create audit entries and create file revisions when a snapshot is small enough.

## Frontend Flow

- `/websites/{id}/files` provides root selection, breadcrumbs, folder listing, Monaco text editing, upload, archive, extraction, trash and revision restore.
- `/websites/{id}/settings/files` is owner-only and configures approved roots.
- Root capabilities disable unavailable actions in the UI, but Laravel enforces the same checks.

## Limits

Configured in `backend/.env`:

- `FILE_MAX_EDIT_BYTES`
- `FILE_MAX_UPLOAD_BYTES`
- `FILE_MAX_DOWNLOAD_BYTES`
- `FILE_MAX_DIRECTORY_ITEMS`
- `FILE_MAX_SEARCH_RESULTS`
- `FILE_MAX_RECURSIVE_ITEMS`
- `FILE_MAX_RECURSIVE_BYTES`
- `FILE_MAX_ARCHIVE_FILES`
- `FILE_MAX_ARCHIVE_UNCOMPRESSED_BYTES`
- `FILE_REVISION_MAX_BYTES`
- `FILE_REVISION_MAX_PER_FILE`
- `FILE_REVISION_RETENTION_DAYS`
- `TRASH_RETENTION_DAYS`

## Not Included Yet

- Monaco diff viewer
- Image editor
- Integrated log viewer
- Coolify deployment actions
- Restricted terminal
- Backup orchestration

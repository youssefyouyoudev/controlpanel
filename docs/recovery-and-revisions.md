# Recovery And Revisions

YouPanel Phase 2 uses two recovery mechanisms.

## File Revisions

Editable saves and overwrite uploads create revision records when the original file is below `FILE_REVISION_MAX_BYTES`.

Revision snapshots are stored under:

```text
backend/storage/app/private/file-revisions
```

Snapshots are private runtime data and must not be committed. The API exposes revision metadata and can restore snapshots for owners and developers.

## Trash

Delete operations move files or directories into:

```text
backend/storage/app/private/trash
```

Trash entries retain the original relative path, item type, size, checksum where available, deletion time and expiration time. Restore refuses to overwrite an existing path.

Permanent deletion is owner-only and requires password confirmation. Expired trash can be pruned with:

```bash
php artisan youpanel:prune-expired-trash --dry-run
php artisan youpanel:prune-expired-trash
```

## Limitations

- Revisions are content snapshots, not full Git commits.
- Directories moved to trash do not create per-file revisions.
- Visual diffs are postponed to a later phase.

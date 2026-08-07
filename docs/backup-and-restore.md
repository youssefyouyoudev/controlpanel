# Backup And Restore

Backups are stored in `storage/app/private/backups` with UUID names, size, checksum and expiration metadata.

Phase 3 supports safe local/manual file-style backups. Database backup profiles exist for encrypted configuration, but production MySQL restore is postponed.

Restore is high risk. Phase 3 verifies checksum and stages restore metadata after owner password confirmation and exact website-name typing. It does not overwrite live production projects.

Retention cleanup deletes only registered backup paths beginning with `backups/`.

# Restricted Console

The Phase 4 console is not a shell. Users submit a known alias such as:

- `artisan.about`
- `artisan.routes`
- `artisan.migrate_status`
- `composer.validate`
- `composer.audit`
- `npm.lint`
- `npm.typecheck`
- `npm.test`
- `git.status`
- `git.log`

Laravel maps aliases to fixed command arrays. Pipes, redirects, shell operators, arbitrary executables, Docker commands, SSH, package-manager mutations and raw command strings are rejected.

Mock mode returns redacted mock output and never starts local processes.


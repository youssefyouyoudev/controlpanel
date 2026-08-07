# Logging And Redaction

Log sources are stored as `website_log_sources` records and configured by owners. Users request a source ID, never a file path.

Controls:

- website authorization
- active source allowlist
- line limits
- redaction for bearer tokens, cookies, passwords, API keys and credential URLs
- sensitive download disabled for non-owners

Redaction is best-effort and should not be treated as a perfect secret scanner.

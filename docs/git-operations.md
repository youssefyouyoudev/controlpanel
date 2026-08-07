# Git Operations

Git integration resolves repository paths from website components inside approved roots.

Implemented:

- branch
- latest commit
- redacted remote URL
- dirty state
- changed file summary
- latest commits
- branches
- fetch action
- fast-forward-only pull action

Pull is blocked when local changes exist. YouPanel never performs force checkout, reset, clean, push or automatic stash in Phase 3.

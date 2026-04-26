---
name: release
description: Create or dry-run a Phel release. Use when Codex is asked to prepare a versioned release, run build/release.sh, verify changelog release content, build the PHAR, tag, push, or inspect a GitHub release.
---

# Release

## Preflight

1. Confirm the branch is `main` and the worktree is clean:
   ```bash
   git branch --show-current
   git status --porcelain
   ```

2. Check the current version:
   ```bash
   grep "VERSION = " src/php/Console/Application/VersionFinder.php
   ```

3. Verify `CHANGELOG.md` has release content since the latest tag:
   ```bash
   git diff $(git describe --tags --abbrev=0)..HEAD -- CHANGELOG.md
   ```

4. Determine the release version:
   - use the user-provided `X.Y.Z` version when present
   - otherwise infer the next minor version

## Run

For a dry run:

```bash
./build/release.sh --dry-run <version>
```

For a release:

```bash
./build/release.sh <version>
```

The release script handles version bump, changelog update, commit, PHAR build, tag, push, and GitHub release attachment.

## Verify

```bash
gh release view v<version>
```

Report the release URL and any verification failures.

## References

- `.github/RELEASE.md`
- `build/release.sh`
- `src/php/Console/Application/VersionFinder.php`

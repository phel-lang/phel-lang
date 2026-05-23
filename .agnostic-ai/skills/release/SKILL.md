---
description: Create a new versioned release with changelog, PHAR build, and GitHub release
argument-hint: "[version or --dry-run]"
disable-model-invocation: true
---

# Release

## Context

!`git branch --show-current`
!`git status --porcelain`
!`grep "VERSION = " src/php/Console/Application/VersionFinder.php`

## Instructions

### Phase 1: Pre-flight Checks

1. Abort if not on `main` or if there are uncommitted changes (see context above).

2. **Check CHANGELOG.md has Unreleased content**:
   ```bash
   git diff $(git describe --tags --abbrev=0)..HEAD -- CHANGELOG.md
   ```
   Warn if `## Unreleased` section is empty.

3. **Determine next version**:
   - If `$ARGUMENTS` provides a version, validate format (X.Y.Z)
   - Otherwise, auto-increment minor: `0.29.0` → `0.30.0`

### Phase 2: Release

4. **If `--dry-run`**, show what would happen and stop:
   ```bash
   ./build/release.sh --dry-run <version>
   ```

5. **Run the release script**:
   ```bash
   ./build/release.sh <version>
   ```

   The script handles: version bump, changelog update, commit, PHAR build, git tag, push, GitHub release with PHAR attachment.

### Phase 3: Verify

6. **Confirm release was created**:
   ```bash
   gh release view v<version>
   ```

7. **Report the release URL** to the user.

## Reference

- Full guide: `.github/RELEASE.md`
- Release script: `build/release.sh`
- Version file: `src/php/Console/Application/VersionFinder.php`

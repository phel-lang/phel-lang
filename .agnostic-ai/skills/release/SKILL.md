---
description: Create a new versioned release with changelog, PHAR build, and GitHub release
argument-hint: "[version or --dry-run]"
disable-model-invocation: true
---

# Release

## Context

!`git branch --show-current`
!`git status --porcelain`
!`grep "LATEST_VERSION = " src/php/Shared/VersionFinder.php`

## Instructions

Follow `.agnostic-ai/rules/releases.md`. Never hand-edit the version, changelog heading or tag.

1. **Pre-flight**: on `main`, clean tree, in sync with `origin/main`, and `## Unreleased` in `CHANGELOG.md` has content (see context above).

2. **Version**: use `$ARGUMENTS` when it holds one (`X.Y.Z` or a pre-release like `1.0.0-rc1`). Otherwise propose the next minor.

3. **Dry run first**:
   ```bash
   ./tools/release.sh --dry-run [--name "Short Name"] <version>
   ```

4. **Stop and get an explicit go** from the user. The real run pushes to `main` and publishes a public release.

5. **Release**:
   ```bash
   ./tools/release.sh [--name "Short Name"] <version>
   ```
   Without a terminal, pass `--name` (otherwise it prompts for one) and `--force` (otherwise it prompts to confirm). Pass `--force` only after the user's go.

6. **Verify** with `gh release view v<version>` and report the release URL.

## Reference

- Full guide: `.github/RELEASE.md`
- Release script: `tools/release.sh`
- Version file: `src/php/Shared/VersionFinder.php`

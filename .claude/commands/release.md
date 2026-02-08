# Release

Help with creating a new release.

## Arguments
- `$ARGUMENTS` - Optional: version number (e.g., `0.30.0`) or `--dry-run` to preview

## Instructions

### Phase 1: Pre-flight Checks

1. **Verify we're on `main` with clean state**:
   ```bash
   git branch --show-current
   git status --porcelain
   ```
   Abort if not on `main` or if there are uncommitted changes.

2. **Check CHANGELOG.md has Unreleased content**:
   ```bash
   git diff $(git describe --tags --abbrev=0)..HEAD -- CHANGELOG.md
   ```
   Warn if `## Unreleased` section is empty.

3. **Show current version**:
   ```bash
   grep "VERSION = " src/php/Console/Application/VersionFinder.php
   ```

4. **Determine next version**:
   - If `$ARGUMENTS` provides a version, validate format (X.Y.Z)
   - Otherwise, auto-increment minor: `0.29.0` → `0.30.0`

### Phase 2: Release

5. **If `--dry-run`**, show what would happen and stop:
   ```bash
   ./build/release.sh --dry-run <version>
   ```

6. **Run the release script**:
   ```bash
   ./build/release.sh <version>
   ```

   The script handles:
   - Updates `VersionFinder.php` with new version
   - Moves CHANGELOG.md Unreleased to versioned section
   - Commits with `chore(release): v<version>`
   - Builds PHAR with `OFFICIAL_RELEASE=true`
   - Creates git tag
   - Pushes commit + tag
   - Creates GitHub release with PHAR attachment

### Phase 3: Verify

7. **Confirm release was created**:
   ```bash
   gh release view v<version>
   ```

8. **Report the release URL** to the user.

## Reference

- Full guide: `.github/RELEASE.md`
- Release script: `build/release.sh`
- Version file: `src/php/Console/Application/VersionFinder.php`

## Example Usage

```
/release
/release 0.30.0
/release --dry-run 0.30.0
```

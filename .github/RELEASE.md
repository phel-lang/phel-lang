# Release

Guide to creating a new release for Phel.

## Quick Start

Run the release script from the repository root:

```bash
./build/release.sh          # Auto-increments minor version (0.28.0 → 0.29.0)
./build/release.sh 0.29.0   # Or specify explicit version
```

That's it. The script handles everything: version bumps, changelog updates, PHAR build, git tag, and GitHub release creation.

## Prerequisites

Before releasing, ensure you have:

- GitHub CLI (`gh`) installed and authenticated: `gh auth login`
- Clean git working directory (no uncommitted changes)
- On the `main` branch
- Content in the "Unreleased" section of CHANGELOG.md

## Release Script Options

```bash
# Standard release
./build/release.sh 0.29.0

# Preview changes without modifying anything
./build/release.sh --dry-run 0.29.0

# Skip prompts for CI automation
./build/release.sh --force 0.29.0

# Skip PHAR build (useful for quick patch releases)
./build/release.sh --skip-phar 0.29.0
```

### What the Script Does

1. Validates version format (X.Y.Z) and ensures new > current
2. Runs pre-flight checks (gh CLI, git state, required files, network)
3. Updates [VersionFinder.php](../src/php/Console/Application/VersionFinder.php)
4. Updates [CHANGELOG.md](../CHANGELOG.md) (moves Unreleased to versioned section)
5. Commits changes with `chore(release): vX.Y.Z`
6. Builds PHAR with `OFFICIAL_RELEASE=true`
7. Creates git tag
8. Pushes commit and tag to remote
9. Creates GitHub release with PHAR attachment and changelog notes

---

## Manual Release

If you need to release manually (e.g., the script fails, you're debugging, or you prefer more control), follow these steps:

### Step 1: Update Version Files

Update the version string in [VersionFinder.php](../src/php/Console/Application/VersionFinder.php):

```php
private const VERSION = '0.29.0';
```

### Step 2: Update Changelog

In [CHANGELOG.md](../CHANGELOG.md), rename the "Unreleased" section to the new version with today's date:

```markdown
## [0.29.0] - 2025-01-19
```

Add a new empty "Unreleased" section at the top.

### Step 3: Commit and Push

```bash
git add src/php/Console/Application/VersionFinder.php CHANGELOG.md
git commit -m "chore(release): v0.29.0"
git push origin main
```

### Step 4: Build the PHAR

Build the PHAR with the official release flag:

```bash
OFFICIAL_RELEASE=true ./build/phar.sh
```

This creates `build/out/phel.phar`.

### Step 5: Create GitHub Release

1. Go to [Releases > New Release](https://github.com/phel-lang/phel-lang/releases/new)
2. Click "Choose a tag" and create a new tag `v0.29.0` from `main`
3. Set the release title (e.g., "v0.29.0")
4. Copy the changelog section for this version into the description
5. Attach the `build/out/phel.phar` file
6. Click "Publish release"

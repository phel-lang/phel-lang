# Release

Guide to creating a new release for Phel.

## Quick Start

Run the release script from the repository root:

```bash
./tools/release.sh          # Auto-increments the minor version (0.49.0 -> 0.50.0)
./tools/release.sh 0.50.0   # Or specify an explicit version
```

That's it. The script handles everything: version bumps, changelog updates, PHAR
build, QA smoke test, git tag, and GitHub release creation. Publishing the release
fires `announce-release.yml`.

**Always release with the script.** Doing it by hand misses steps: it is what
updates `resources/agents/VERSION`, moves the `## Unreleased` section itself (so
leave that section populated and never pre-convert it), and smoke-tests the PHAR
before anything is pushed. Run `--dry-run` first; the real run pushes to `main`
and publishes publicly.

## Prerequisites

Before releasing, ensure you have:

- GitHub CLI (`gh`) installed and authenticated: `gh auth login`
- Clean git working directory (no uncommitted changes)
- On the `main` branch
- Content in the "Unreleased" section of CHANGELOG.md

## Release Script Options

```bash
# Standard release
./tools/release.sh 0.50.0

# Preview everything, restore the files, no side effects
./tools/release.sh --dry-run 0.50.0

# Skip the confirmation prompt (CI automation)
./tools/release.sh --force 0.50.0

# Skip the PHAR build (also skips the QA smoke test that runs against it)
./tools/release.sh --skip-phar 0.50.0

# Build the PHAR but skip the QA suite
./tools/release.sh --skip-qa 0.50.0

# Name the release; omit to let the release notes suggest one
./tools/release.sh --name "Life, PHP & Everything" 0.50.0
```

### What the Script Does

1. Validates version format (`X.Y.Z`, or `X.Y.Z-rc1` for a pre-release) and
   ensures new > current
2. Runs pre-flight checks (gh CLI, on `main`, in sync with `origin/main`, clean
   tree, `## Unreleased` still populated, tag absent, network)
3. Updates `LATEST_VERSION` in [VersionFinder.php](../src/php/Shared/VersionFinder.php)
4. Updates [CHANGELOG.md](../CHANGELOG.md) (moves Unreleased to a versioned
   section and opens a fresh empty one)
5. Updates [resources/agents/VERSION](../resources/agents/VERSION) (targeted
   phel-lang release for agent docs/tests)
6. Commits changes with `chore(release): vX.Y.Z`
7. Builds the PHAR with `OFFICIAL_RELEASE=true` and QA-smoke-tests it
8. Creates the git tag
9. Pushes commit and tag to `main`
10. Creates the GitHub release with notes, contributors and the PHAR attached,
    which fires `announce-release.yml`

A pre-release (`tools/release.sh 1.0.0-rc1`) differs in three ways: step 4 is
skipped so `## Unreleased` stays where it is, the notes come from that section
as it stands, and step 10 publishes with `--prerelease`.

---

## What the script does not do at a major

Two normative documents describe the pre-1.0 world, and no tooling updates them.
Before tagging `X.0.0`, do both by hand:

| Document | Step |
|---|---|
| [docs/stability.md](../docs/stability.md) | Delete the paragraph marked `RELEASE-STEP(1.0.0)`. Until the major it reads as a *target*; from the major the promises are in force. |
| [docs/migration/upgrade-0.49-to-1.0.md](../docs/migration/upgrade-0.49-to-1.0.md) | Check the release range it claims to cover still matches the releases that exist. |

`grep -rn 'RELEASE-STEP(' docs/` finds every marker.

---

## Manual Release

Only when the script itself is broken. It is the supported path, and every step
below is a step the script already performs.

### Step 1: Update Version Files

Update the constant in [VersionFinder.php](../src/php/Shared/VersionFinder.php).
Note the `v` prefix:

```php
public const string LATEST_VERSION = 'v0.50.0';
```

### Step 2: Update Changelog

In [CHANGELOG.md](../CHANGELOG.md), rename the "Unreleased" section to the new version with today's date:

```markdown
## [0.50.0] - 2026-08-01
```

Add a new empty "Unreleased" section at the top.

### Step 3: Update resources/agents/VERSION

```bash
echo "0.50.0" > resources/agents/VERSION
```

Tracked by `composer test-agents`, which runs the bundled example projects
against that release.

### Step 4: Commit and Push

```bash
git add src/php/Shared/VersionFinder.php CHANGELOG.md resources/agents/VERSION
git commit -m "chore(release): v0.50.0"
git push origin main
```

### Step 5: Build the PHAR

Build the PHAR with the official release flag:

```bash
OFFICIAL_RELEASE=true ./build/phar.sh
```

This creates `build/out/phel.phar`.

### Step 6: Create GitHub Release

1. Go to [Releases > New Release](https://github.com/phel-lang/phel-lang/releases/new)
2. Click "Choose a tag" and create a new tag `v0.50.0` from `main`
3. Set the release title (e.g., "v0.50.0")
4. Copy the changelog section for this version into the description
5. Attach the `build/out/phel.phar` file
6. Click "Publish release"

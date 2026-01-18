# Release

Guide to creating a new release.

## Automated Release (Recommended)

Use the release automation script for a streamlined release process:

```bash
# Standard release
./build/release.sh 0.28.0

# Preview changes without modifying anything
./build/release.sh --dry-run 0.28.0

# Skip prompts for CI automation
./build/release.sh --force 0.28.0

# Skip PHAR build
./build/release.sh --skip-phar 0.28.0
```

The script automatically:

1. Validates version format (X.Y.Z) and ensures new > current
2. Runs pre-flight checks (gh CLI, git state, required files, network)
3. Updates [VersionFinder.php](../src/php/Console/Application/VersionFinder.php)
4. Updates [CHANGELOG.md](../CHANGELOG.md) (moves Unreleased to versioned section)
5. Commits changes with `chore(release): vX.Y.Z`
6. Builds PHAR with `OFFICIAL_RELEASE=true`
7. Creates git tag
8. Pushes commit and tag to remote
9. Creates GitHub release with PHAR attachment and changelog notes

### Prerequisites

- GitHub CLI (`gh`) installed and authenticated: `gh auth login`
- Clean git working directory
- On `main` branch
- Content in the Unreleased section of CHANGELOG.md

## Manual Release

If you prefer to release manually:

1. Update the version in [VersionFinder.php](../src/php/Console/Application/VersionFinder.php)
2. Update the version in [CHANGELOG.md](../CHANGELOG.md)
3. Commit and push these changes
4. Create a [new release](https://github.com/phel-lang/phel-lang/releases/new) from GitHub
   1. Create the tag from the main branch (the commits from step 3)
   2. Build the PHAR with an official release flag and attach it to the release:
      ```bash
      OFFICIAL_RELEASE=true build/phar.sh
      ```
      Then upload `build/out/phel.phar` to the GitHub release as an attachment
   3. Publish the release (this creates the tag on GitHub)

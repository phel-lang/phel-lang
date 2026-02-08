Help with creating a new release.

Reference: `.github/RELEASE.md` and `build/release.sh`

Steps:
1. Ensure we're on the `main` branch with a clean working directory
2. Check that `CHANGELOG.md` has content in the `## Unreleased` section
3. Show the current version from `src/php/Console/Application/VersionFinder.php`
4. Suggest the next version (auto-increment minor)
5. Guide through running `./build/release.sh [version]`

$ARGUMENTS can specify the version number, or use `--dry-run` to preview.

# Releases

**ALWAYS cut a release with `tools/release.sh` (or the `/release` skill, which wraps it). Never hand-edit version/changelog/tag manually.**

Hand-rolling misses steps (`resources/agents/VERSION`), uses the wrong commit message, double-edits the CHANGELOG (the script moves `## Unreleased` itself), and skips the PHAR build + QA smoke test + GitHub release.

## The script does it all

`tools/release.sh [--name NAME] [VERSION]`:

1. Pre-flight: on `main`, in sync with `origin/main`, clean tree; `## Unreleased` still holds its content; tag absent.
2. Updates `VersionFinder.php` (`LATEST_VERSION`), `CHANGELOG.md` (`## Unreleased` → `## [x.y.z](compare/...) - DATE` + fresh empty Unreleased), `resources/agents/VERSION`.
3. Commits `chore(release): vX.Y.Z`, builds the PHAR, QA-smoke-tests it.
4. Tags `vX.Y.Z`, pushes commit + tag to `main`, creates the GitHub release (notes + TL;DR + contributors + PHAR). Publishing auto-fires `announce-release.yml`.

## Rules

- Leave the CHANGELOG `## Unreleased` populated; the script moves it. Never pre-convert it to a versioned heading.
- Name is short (1-3 words), content-themed: `--name "Life, PHP & Everything"`. Omit for AI-suggested names.
- **`--dry-run` first** — previews everything, restores files, no side effects.
- The real run pushes to `main` and publishes a public release: outward + irreversible. Get explicit human go first.
- A pre-release version (`1.0.0-rc1`) is accepted: the script leaves `## Unreleased` in place and publishes to GitHub as a pre-release.
- Full guide: `.github/RELEASE.md`.

## What the script does NOT do at a major

Two normative documents describe the pre-1.0 world and no tooling updates them.
Before tagging `X.0.0`, do both by hand:

- `docs/stability.md`: delete the paragraph marked `RELEASE-STEP(1.0.0)`. Until then the page reads as a *target*; from the major it is in force.
- `docs/migration/upgrade-0.49-to-1.0.md`: check the range it claims to cover still matches the releases that exist.

Grep for `RELEASE-STEP(` to find every marker.

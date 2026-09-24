---
name: releases
description: How a Phel release is cut, and what needs a human go.
globs: CHANGELOG.md,tools/release*,.github/RELEASE.md,.github/workflows/announce-release.yml,src/php/Shared/VersionFinder.php,resources/agents/VERSION,docs/stability.md,docs/migration/**
---

# Releases

Cut a release only with `tools/release.sh`, or the `/release` skill that wraps it. It bumps `VersionFinder.php`, `resources/agents/VERSION` and the CHANGELOG heading, commits `chore(release): vX.Y.Z`, builds and smoke-tests the PHAR, tags, pushes and publishes the GitHub release. Doing any of that by hand misses a step.

- Leave `## Unreleased` populated. The script moves it. Never pre-convert it to a versioned heading.
- `--dry-run` first. It previews everything and restores the files.
- The real run pushes to `main` and publishes a public release. Get an explicit human go.
- Name: 1-3 words about the content, `--name "Floor Raised"`. Without a terminal pass `--name`, or the script prompts.
- A pre-release (`1.0.0-rc1`) keeps `## Unreleased` and publishes as a GitHub pre-release.
- At a major (`X.0.0`), edit two docs by hand first. In `docs/stability.md`, delete the paragraph marked `RELEASE-STEP(1.0.0)`. In `docs/migration/upgrade-0.49-to-1.0.md`, check that the version range it covers matches the releases that exist.

Full guide: `.github/RELEASE.md`.

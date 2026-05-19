# tools/

Developer tooling for the phel-lang repository. Scripts that operate *on* the
project (release, ecosystem upgrade, agent validation, git hooks) live here.
Artifact-producing scripts (PHAR builder) live in [`build/`](../build/).

## Quick map

| File | Purpose | Tests |
|---|---|---|
| [`release.sh`](release.sh) | Cut a new phel-lang release: bump version, update CHANGELOG, build PHAR, tag, push, create GitHub release. | [`release-test.sh`](release-test.sh) |
| [`release-lib.sh`](release-lib.sh) | Pure helpers for `release.sh` (semver, changelog, backup/restore). Sourced by the orchestrator and the tests. | — |
| [`upgrade-ecosystem.sh`](upgrade-ecosystem.sh) | After a release, walk every sibling repo that depends on `phel-lang/phel-lang` and let Claude bump the constraint, run tests, commit, push, open a PR. | [`upgrade-ecosystem-test.sh`](upgrade-ecosystem-test.sh) |
| [`upgrade-ecosystem-lib.sh`](upgrade-ecosystem-lib.sh) | Pure helpers for `upgrade-ecosystem.sh` (version normalize, caret derivation, composer.json parsing, portable timeout). | — |
| [`validate-agents.sh`](validate-agents.sh) | Sanity checks for `resources/agents/`. Wired to `composer test-agents`. | — |
| `bashunit` | Vendored bashunit runner used by both `*-test.sh` files. | — |
| `create-pr` | Helper for opening PRs from a branch. | — |
| `git-hooks/` | Project git hooks installable into `.git/hooks/`. | — |

## Conventions

Bash entry points use the **orchestrator + lib + test** triple:

```
foo.sh          # CLI / orchestration. Side effects live here.
foo-lib.sh      # Pure functions. No I/O, no git/network. Sourceable by tests.
foo-test.sh     # bashunit suite for foo-lib.sh + CLI smoke tests for foo.sh.
```

CI runs `tools/bashunit tools/*-test.sh --simple` so new tests are picked up by
the glob automatically — name the file `<name>-test.sh` and it ships.

## Running

```bash
# Cut a release (auto-bumps minor; or pass an explicit version)
./tools/release.sh
./tools/release.sh 0.40.0
./tools/release.sh --dry-run 0.40.0          # safe preview
# See .github/RELEASE.md for the full workflow.

# Bump phel-lang across every ecosystem repo after a release
./tools/upgrade-ecosystem.sh --dry-run        # plan + per-repo preflight, no side effects
./tools/upgrade-ecosystem.sh --yes            # real run (branch + PR per repo)
./tools/upgrade-ecosystem.sh --parallel=4 --yes
./tools/upgrade-ecosystem.sh --direct-push --yes   # commit + push to default branch, no PR

# Run the bash test suite
composer test-tools      # same as: ./tools/bashunit tools/*-test.sh --simple

# Validate agent resources
composer test-agents     # same as: ./tools/validate-agents.sh
```

## Adding a new tool

1. Drop the script in `tools/`.
2. If it has non-trivial logic, split out a `*-lib.sh` and write a
   `*-test.sh` next to it. Mirror the existing pattern in `release*` /
   `upgrade-ecosystem*`.
3. If it should be invokable via composer (test-* style), add an entry to
   `composer.json` → `scripts`.
4. CI picks up new `*-test.sh` files automatically; no workflow change needed.

## Not here

- PHAR build (`phar.sh`, `build-phar.php`, `preload.php`) — produces an
  artifact, so it lives in [`build/`](../build/).
- Generated output (`build/out/`, `build/.phar-cache/`,
  `tools/.upgrade-logs/`) is gitignored.

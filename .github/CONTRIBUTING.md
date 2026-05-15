# Contributing to Phel

- [Report a bug](https://github.com/phel-lang/phel-lang/issues/new?labels=bug&template=BUG.md)
- [Propose a feature](https://github.com/phel-lang/phel-lang/issues/new?labels=enhancement&template=FEATURE_REQUEST.md)
- [Open a pull request](https://github.com/phel-lang/phel-lang/pulls)

For bigger changes, open an issue first so we can discuss.

## Understanding the Codebase

Two-language project — compiler/runtime in PHP, standard library in Phel:

```
src/php/      → Compiler, runtime, CLI tools (PHP, Gacela modules)
src/phel/     → Core standard library (Phel source, Clojure-inspired)
tests/php/    → PHPUnit tests
tests/phel/   → Phel tests
docs/         → Guides, examples, internals
```

Compiler pipeline: Lexer → Parser → Reader → Analyzer → Emitter. Each `src/php/` module exposes a Facade as its public API.

For a deeper architecture overview, see [AGENTS.md](../AGENTS.md).

## Quick Start

Requires **PHP 8.4+** and **Composer**.

```bash
composer install
composer test       # must pass before you start
```

## Making Changes

1. Branch from `main` (prefixes: `feat/`, `fix/`, `ref/`, `docs/`)
2. Write code and tests
3. Run the [targeted test command](#which-tests-to-run) for your change
4. Run `composer fix` to auto-format
5. Open a PR following the [PR template](PULL_REQUEST_TEMPLATE.md)

## Commands

**Day-to-day:**

```bash
composer test       # Run everything (quality + compiler + core)
composer fix        # Auto-format (Rector + CS Fixer)
composer create-pr  # Open a PR
```

**Testing:**

```bash
composer test-compiler  # PHPUnit (unit + integration)
composer test-core      # Phel's own test framework
composer test-quality   # Static analysis + linting
```

**Static analysis:**

```bash
composer psalm      # Psalm
composer phpstan    # PHPStan
composer csrun      # Code style check
composer rector     # Rector check
```

**Benchmarks:**

```bash
composer phpbench       # Run benchmarks
composer phpbench-base  # Create baseline
composer phpbench-ref   # Compare to baseline
```

**Build:**

```bash
./build/phar.sh         # Produce build/out/phel.phar
```

## Which Tests to Run

| What you changed | Command | Notes |
|------------------|---------|-------|
| `src/php/**` | `composer test-compiler` | PHPUnit unit + integration |
| `src/phel/**` | `composer test-core` | Phel core tests |
| Single PHP module | `./vendor/bin/phpunit --filter=ModuleName` | Fastest for focused work |
| Single Phel file | `./bin/phel test tests/phel/<file>` | Target specific test |
| Code style only | `composer test-quality` | Static analysis |
| Mixed PHP + Phel | `composer test` | Run everything |

See the [testing docs](https://phel-lang.org/documentation/testing/) for more on the Phel test framework.

## Git Hooks

Optional but recommended:

```bash
tools/git-hooks/init.sh
```

Installs a pre-commit hook that runs `composer test` before each commit. Skip with `--no-verify` in a pinch, but always run `composer test` before pushing.

## Commit Messages

We use [conventional commits](https://www.conventionalcommits.org/):

```
feat: add new string utility function
fix: correct off-by-one in map iteration
ref: simplify compiler analyzer phase
docs: update quickstart guide
test: add integration tests for try/catch
chore: bump phpstan to v2
```

## Changelog

For user-facing changes (`feat:`, `fix:`), update `CHANGELOG.md` under `## Unreleased` with [Keep a Changelog](https://keepachangelog.com/) subsections: `Added`, `Changed`, `Fixed`, `Removed`. Add sub-headings (e.g. `#### Core Language`, `#### REPL & Tooling`) when multiple areas are affected.

## Where to Start

Issues labeled [`good first issue`](https://github.com/phel-lang/phel-lang/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22) are scoped and newcomer-friendly.

| Interest | Start here | Tests |
|----------|-----------|-------|
| Fix/add a language feature | `src/php/Compiler/` | `composer test-compiler` |
| Improve the standard library | `src/phel/` | `composer test-core` |
| Improve tooling (REPL, formatter) | `src/php/Run/`, `src/php/Formatter/` | `composer test-compiler` |
| Improve documentation | `docs/` | none |
| Add PHP interop wrappers | `src/php/Interop/` | `composer test-compiler` |

## Troubleshooting

**`composer test` fails after fresh clone?** Check PHP 8.4+ (`php -v`) and run `composer install` again.

**Tests pass locally but CI fails?** CI runs the full suite — make sure you ran `composer test`, not a subset.

**Code style errors?** Run `composer fix`.

---

[Code of Conduct](CODE_OF_CONDUCT.md) · [MIT License](https://github.com/phel-lang/phel-lang/blob/master/LICENSE) · [Discussions](https://github.com/phel-lang/phel-lang/discussions)

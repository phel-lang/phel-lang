# Contributing to Phel

Want to contribute? Here's how:

- [Report a bug](https://github.com/phel-lang/phel-lang/issues/new?labels=bug&template=BUG.md)
- [Propose a feature](https://github.com/phel-lang/phel-lang/issues/new?labels=enhancement&template=FEATURE_REQUEST.md)
- [Open a pull request](https://github.com/phel-lang/phel-lang/pulls)

For bigger changes, open an issue first so we can discuss.

## Understanding the Codebase

Phel is a **two-language project** — the compiler and runtime are written in PHP, while the standard library is written in Phel itself (the language it compiles):

```
src/php/      → Compiler, runtime, CLI tools (PHP)
src/phel/     → Core standard library (Phel source)
tests/php/    → PHPUnit tests for the PHP side
tests/phel/   → Phel tests for the standard library
docs/         → Guides, examples, and internals docs
```

**PHP side** (`src/php/`): Organized into modules (Api, Build, Compiler, Config, Console, etc.) using the [Gacela](https://github.com/gacela-project/gacela) framework. Each module has a Facade as its public API. The compiler pipeline flows: Lexer → Parser → Reader → Analyzer → Emitter.

**Phel side** (`src/phel/`): The standard library (`core`, `str`, `html`, `http`, `json`, `test`, etc.) written in Phel itself — Lisp syntax inspired by Clojure.

For a deeper architecture overview, see [AGENTS.md](../AGENTS.md).

## Quick Start

You'll need **PHP 8.3+** and **Composer**.

```bash
# Fork and clone, then:
composer install

# Verify everything works:
composer test
```

If `composer test` passes, you're ready to contribute.

## Making Changes

1. Branch from `main` (use prefixes: `feat/`, `fix/`, `ref/`, `docs/`)
2. Write your code — and tests!
3. Run `composer test` (or the [targeted test command](#which-tests-to-run) for your change)
4. Run `composer fix` to auto-format
5. Open a PR following the [PR template](PULL_REQUEST_TEMPLATE.md)

## Commands

**Day-to-day:**

```bash
composer test       # Run everything (quality + compiler + core)
composer fix        # Auto-format code (Rector + CS Fixer)
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

## Tests

We have two test suites matching the two-language structure:

- **Compiler tests** (PHPUnit) in `tests/php/` — for changes to `src/php/`
- **Core library tests** (Phel) in `tests/phel/` — for changes to `src/phel/`

See the [testing docs](https://phel-lang.org/documentation/testing/) for more on the Phel test framework.

### Which Tests to Run

| What you changed | Command | Notes |
|------------------|---------|-------|
| `src/php/**` | `composer test-compiler` | PHPUnit unit + integration |
| `src/phel/**` | `composer test-core` | Phel core tests |
| Single PHP module | `./vendor/bin/phpunit --filter=ModuleName` | Fastest for focused work |
| Single Phel file | `./bin/phel test tests/phel/<file>` | Target specific test |
| Code style only | `composer test-quality` | Static analysis |
| Mixed PHP + Phel | `composer test` | Run everything |

## Git Hooks

Optional but recommended:

```bash
tools/git-hooks/init.sh
```

This installs a **pre-commit hook** that runs `composer test-all` before each commit. It takes ~20 seconds and catches issues before they reach CI.

You can skip hooks with `--no-verify` in a pinch, but always run `composer test` before pushing.

## Updating the Changelog

For user-facing changes (`feat:`, `fix:`), update `CHANGELOG.md` under `## Unreleased` using [Keep a Changelog](https://keepachangelog.com/) format:

```markdown
## Unreleased

### Added
- Description of new feature (#issue-number)

### Changed
- Description of change (#issue-number)

### Fixed
- Description of bug fix (#issue-number)

### Removed
- Description of removal (#issue-number)
```

Use subsections (e.g., `#### Core Language`, `#### REPL & Tooling`) when multiple areas are affected.

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

## Where to Start

Not sure where to begin? Look for issues labeled [`good first issue`](https://github.com/phel-lang/phel-lang/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22) — these are scoped, well-defined tasks suitable for newcomers.

You can also pick a path based on your interest:

| Interest | Start here | Tests |
|----------|-----------|-------|
| Fix/add a language feature | `src/php/Compiler/` | PHPUnit (`composer test-compiler`) |
| Improve the standard library | `src/phel/` | Phel tests (`composer test-core`) |
| Improve tooling (REPL, formatter) | `src/php/Run/`, `src/php/Formatter/` | PHPUnit (`composer test-compiler`) |
| Improve documentation | `docs/` | No tests needed |
| Add PHP interop wrappers | `src/php/Interop/` | PHPUnit (`composer test-compiler`) |

## Troubleshooting

**`composer test` fails after fresh clone?**
Make sure you have PHP 8.3+ (`php -v`) and a recent Composer (`composer --version`). Run `composer install` again.

**Tests pass locally but CI fails?**
CI runs the full `composer test` suite. Make sure you ran that, not just a subset.

**Code style errors?**
Run `composer fix` — it auto-formats everything with Rector and CS Fixer.

## Reporting Bugs

Include:

- What happened vs what you expected
- Steps to reproduce (code samples help)
- Anything else you've tried

Post code as text, not screenshots.

## Code of Conduct

We follow the [Contributor Code of Conduct](CODE_OF_CONDUCT.md).

## License

Contributions are under the [MIT License](https://github.com/phel-lang/phel-lang/blob/master/LICENSE).

## Questions?

Open a [discussion](https://github.com/phel-lang/phel-lang/discussions) or reach out.

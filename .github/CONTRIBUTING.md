# Contributing to Phel

Want to contribute? Here's how:

- [Report a bug](https://github.com/phel-lang/phel-lang/issues/new?labels=bug&template=BUG.md)
- [Propose a feature](https://github.com/phel-lang/phel-lang/issues/new?labels=enhancement&template=FEATURE_REQUEST.md)
- [Open a pull request](https://github.com/phel-lang/phel-lang/pulls)

For bigger changes, open an issue first so we can discuss.

## Quick Start

You'll need PHP 8.3+ and Composer.

```bash
# Fork and clone, then:
composer install
```

## Making Changes

1. Branch from `main`
2. Write your code (and tests!)
3. Run `composer test`
4. Run `composer fix` to format
5. Open a PR

## Project Layout

```
src/php/      → Compiler (PHP)
src/phel/     → Core library (Phel)
tests/php/    → PHPUnit tests
tests/phel/   → Phel tests
```

## Commands

**Day-to-day:**

```bash
composer test       # Run everything
composer fix        # Auto-format code
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

We have two test suites:

- **Compiler tests** (PHPUnit) in `tests/php/`
- **Core library tests** (Phel) in `tests/phel/` — see the [testing docs](https://phel-lang.org/documentation/testing/)

Changed the compiler? Add PHPUnit tests. Changed the core library? Add Phel tests.

## Git Hooks

Optional but handy:

```bash
tools/git-hooks/init.sh
```

You can skip them with `--no-verify`, but run `composer test` before pushing.

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

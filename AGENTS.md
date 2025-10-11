# Repository Guidelines

## Project Structure & Module Organization

Core functionality sits in `src/php`, where each module has its own README: `Lang` defines persistent data types, `Compiler` powers the lex/parse/analyze/emit pipeline, `Run` handles namespace execution, `Console` wires the Symfony CLI, `Command` centralizes shared command helpers, and `Api` exposes REPL and introspection services. Supporting modules cover formatting (`Formatter`), PHP interop/export (`Interop`), printing (`Printer`), filesystem adapters (`Filesystem`), build orchestration (`Build`), configuration models (`Config`), and shared constants plus facades (`Shared`). Entry points live in `Phel.php` and `bin/`, distributable artifacts and scripts sit under `build/`, documentation and examples reside in `docs/`, and `tests/php` is split into `Unit`, `Integration`, and `Benchmark`; temporary outputs land in `data/` or `var/`.

## Build, Test, and Development Commands

- `composer install`: sync PHP dependencies; required before running any tooling.
- `composer test`: full quality gate (static analysis, compiler tests, runtime tests); mirrors CI.
- `composer test-compiler`: PHPUnit suites (`unit`, `integration`) with JUnit output at `data/log-junit.xml`.
- `composer test-core`: exercises Phel scripts via `bin/phel test`.
- `composer test-compiler:coverage`: generates HTML/XML coverage under `data/coverage-*` (requires
  `XDEBUG_MODE=coverage`).
- `composer phpstan` / `composer psalm`: static analysis tuned for PHP 8.3.
- `composer csrun` (dry lint) and `composer csfix`: enforce formatting; `composer fix` chains Rector and CS Fixer when
  code motion is expected.
- `./build/phar.sh`: creates `build/out/phel.phar` for distribution testing.

## Coding Style & Naming Conventions

Follow PSR-12 (4-space indentation, braces on new lines) with strict types and short array syntax; the fixer also
enforces ordered imports, single quotes, and expressive docblocks. Each PHP file should start with
`declare(strict_types=1);`. Class and namespace names follow PascalCase, methods camelCase, and test doubles should end
with `Stub`/`Fake` as seen in `tests/php`. Use `composer csrun` before committing and rely on Rector for mechanical
refactors instead of hand-editing repetitive changes.

## Testing Guidelines

PHPUnit tests live alongside fixtures in `tests/php/{Unit,Integration}`, named `*Test.php`. Lightweight runtime checks
for Phel modules belong in `tests/php/Benchmark` or `tests/php/Integration` depending on scope. Run `composer test`
locally before every PR; when touching the compiler or runtime, add focused unit tests plus an integration scenario
executed through the Phel CLI. Collect coverage with `composer test-compiler:coverage` when validating critical changes
and attach summaries to the PR if coverage shifts.

## Commit & Pull Request Guidelines

Commit history favors conventional prefixes (`fix:`, `feat:`, `chore:`) followed by a terse imperative summary; include
issue refs (e.g., `#123`) when applicable. Squash incidental WIP commits before opening a PR. PR descriptions should
follow the template's Background, Goal, and Changes sections, link related issues, and mention CHANGELOG updates when
the change is user-facing. Attach test evidence (command output or screenshots) whenever behavior or CLI interaction
changes.

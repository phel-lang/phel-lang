# Repository Guidelines

## Project Structure & Module Organization

Phel is a Lisp that compiles to PHP, inspired by Clojure and Janet. The codebase has two source trees:

- **`src/php/`** — PHP runtime and compiler (PSR-4: `Phel\`). Key modules: `Lang` (persistent data types), `Compiler` (lex/parse/analyze/emit pipeline), `Run` (namespace execution and REPL), `Console` (Symfony CLI), `Command` (shared command helpers), `Build` (build orchestration), `Config` (configuration), and `Shared` (constants and facades).
- **`src/phel/`** — Core library written in Phel itself: `core`, `str`, `html`, `http`, `json`, `test`, `repl`, `walk`, `pprint`, `debug`, `mock`.

Entry points live in `Phel.php` and `bin/`, distributable artifacts and scripts sit under `build/`, documentation and examples reside in `docs/`, and `tests/php` is split into `Unit`, `Integration`, and `Benchmark`; Phel-level tests are in `tests/phel/`. Temporary outputs land in `data/` or `var/`.

## Build, Test, and Development Commands

- `composer install`: sync PHP dependencies; required before running any tooling.
- `composer test`: full quality gate (static analysis, compiler tests, core tests); mirrors CI.
- `composer test-quality`: static analysis only (cs-fixer, psalm, phpstan, rector).
- `composer test-compiler`: PHPUnit suites (`unit`, `integration`).
- `composer test-core`: exercises Phel core tests via `bin/phel test`.
- `composer fix`: auto-fix code style (chains Rector and CS Fixer).
- `composer phpstan` / `composer psalm`: static analysis tuned for PHP 8.3.
- `composer phpbench`: run benchmarks; `composer phpbench-base` (baseline), `composer phpbench-ref` (compare).
- `./build/phar.sh`: creates `build/out/phel.phar` for distribution testing.

## Coding Style & Naming Conventions

Follow PER-CS 3.0 enforced by php-cs-fixer and Rector, with strict types and short array syntax. Each PHP file starts
with `declare(strict_types=1);`. Prefer `final` classes unless inheritance is explicitly needed, and use `readonly`
properties where possible. Class and namespace names follow PascalCase, methods camelCase. Use `composer fix` to
auto-format and rely on Rector for mechanical refactors.

Phel source uses `;` for line comments (not `#`), `;;` for standalone comments, and kebab-case for functions and
variables. Public functions should have `:doc`, `:see-also`, and `:example` metadata.

## Testing Guidelines

PHPUnit tests live alongside fixtures in `tests/php/{Unit,Integration}`, named `*Test.php` with snake_case method names
(e.g., `test_it_does_something()`). Run `composer test` locally before every PR; when touching the compiler or runtime,
add focused unit tests plus an integration scenario. Integration test fixtures use `--PHEL--`/`--PHP--` format in
`.test` files under `tests/php/Integration/Fixtures/`.

## Commit & Pull Request Guidelines

Commit history uses conventional prefixes (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`, `perf:`) followed by a
terse imperative summary; include issue refs (e.g., `#123`) when applicable. PR descriptions should follow
`.github/PULL_REQUEST_TEMPLATE.md` exactly (including emoji prefixes), link related issues with "Closes #X", and update
`CHANGELOG.md` under `## Unreleased` for user-facing changes.

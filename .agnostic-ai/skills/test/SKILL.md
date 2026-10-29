---
description: Run the narrowest test scope for a change.
argument-hint: "[scope-or-filter]"
disable-model-invocation: true
allowed-tools:
  - "shell(composer *)"
  - "shell(./vendor/bin/phpunit *)"
  - "shell(./bin/phel *)"
---

# Test Runner

Follow `.agnostic-ai/rules/build-test-and-development-commands.md` for gate timing and required hooks.

## Select the scope

1. Honor an explicit file, filter or suite. With no argument, or `quick`, inspect `git status --short`, `git diff` and `git diff --cached`; choose checks for the changed behavior rather than defaulting to every suite.
2. Cover the behavior and its affected integration boundary. For a compiler change, include the relevant compilation fixture or runtime path; for PHP/Phel interop, exercise the Phel caller too. Broaden for failures or effects that a focused run cannot cover.
3. Run affected quality checks on the final diff. Formatter and Rector dry runs accept changed PHP paths; static analysis accepts affected `src/` paths, never tests (ADR 0010). Coordinate expensive checks across workers and never overlap typechecks.
4. Reuse passing results on unchanged code, including a commit hook's gate. After a fix, repeat the affected checks. Report exact commands, counts and any coverage limit.

## Commands

Install dependencies with `composer install` before PHP/Phel tooling if `vendor/` is missing or the lock file changed.

| Scope | Command and coverage |
|---|---|
| PHP class or method | `./vendor/bin/phpunit --filter=<Class-or-method>` |
| PHP test file or module | `./vendor/bin/phpunit tests/php/<path>`; include its affected integration tests |
| Compiler fixture | `./vendor/bin/phpunit --testsuite=integration --filter=<fixture-stem>` |
| Phel test file | `./bin/phel test tests/phel/<file>` |
| `unit` / `integration` | `composer test-unit` / `composer test-integration` (integration uses paratest) |
| `compiler` | `composer test-compiler`: PHP unit and integration suites |
| `core` | `composer test-core`: Phel workers, then timing-sensitive tests alone; `composer test-core:serial` runs serially |
| `quality` | `composer test-quality`: formatter dry run, Psalm, PHPStan, Rector and config validation |
| `all` | `COMPOSER_PROCESS_TIMEOUT=0 composer test`: quality, compiler and core, matching CI |
| Formatting fixes | `composer fix`: Rector then CS Fixer; prefer explicit changed paths when isolating a change |
| `bench` | `composer phpbench`; select affected subjects and follow `.agnostic-ai/skills/benchmark/SKILL.md` |
| PHAR packaging | `./build/phar.sh`: builds `build/out/phel.phar` |

An explicit `all`, or a change whose effects cannot be covered by narrower checks, uses the full gate. Do not repeat a completed full gate unless subsequent changes affect its results.

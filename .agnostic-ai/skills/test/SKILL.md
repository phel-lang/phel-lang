---
description: Run the narrowest tests and checks for a change.
argument-hint: "[scope-or-filter]"
disable-model-invocation: true
allowed-tools:
  - "shell(composer *)"
  - "shell(./vendor/bin/phpunit *)"
  - "shell(./bin/phel *)"
---

# Test Runner

Gate timing, reuse and hooks: `.agnostic-ai/rules/build-test-and-development-commands.md`.

## Select the scope

1. Honor an explicit file, filter or suite. Otherwise read `git status --short`, `git diff` and `git diff --cached`, and pick checks for the changed behavior.
2. Cover its integration boundary: a compiler change runs the matching fixture or runtime path; PHP/Phel interop exercises the Phel caller too.
3. Run quality checks on the final diff. Formatter and Rector dry runs take changed PHP paths; static analysis takes affected `src/` paths.
4. Report exact commands, counts and any coverage limit.

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
| Shell tooling (`tools/*.sh`) | `composer test-tools` |
| Agent specs (`.agnostic-ai/`) | the gate in `.agnostic-ai/rules/agent-tooling.md` |
| Downstream agent package (`resources/agents/`) | `composer test-agents` |
| Prose only | review the diff and check every link |

Use `all` only when asked or when narrower checks cannot cover the change.

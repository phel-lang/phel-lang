# Phel Language

Functional programming language compiling to PHP. Lisp dialect inspired by Clojure and Janet.

## Architecture

```
src/php/       → Compiler, runtime, CLI (PHP, PSR-4: Phel\)
src/phel/      → Core library (Phel source: core, str, html, http, json, test)
tests/php/     → PHPUnit tests (unit + integration)
tests/phel/    → Phel test files
build/         → PHAR build scripts, release tooling
```

Each module in `src/php/` has a `CLAUDE.md` — **read it before modifying a module**. It documents the Gacela pattern, public API, dependencies, and constraints.

## Testing

```bash
composer test              # All tests (quality + compiler + core)
composer test-quality      # Static analysis: cs-fixer, psalm, phpstan, rector
composer test-compiler     # PHPUnit unit + integration tests
composer test-core         # Phel core tests (./bin/phel test)
composer fix               # Auto-fix: rector + cs-fixer
```

### Test Mapping

Run the **minimum** test scope for your changes:

| Changed | Command | Notes |
|---------|---------|-------|
| `src/php/**` | `composer test-compiler` | PHPUnit unit + integration |
| `src/phel/**` | `composer test-core` | Phel core tests |
| `src/php/Compiler/**` | `composer test-compiler` | Full compiler suite |
| Single PHP module | `./vendor/bin/phpunit --filter=ModuleName` | Fastest for focused work |
| Single Phel file | `./bin/phel test tests/phel/<file>` | Target specific test |
| Any `.php` style change | `composer test-quality` | Static analysis only |
| Mixed PHP + Phel | `composer test` | Run everything |

## Git

- Conventional commits: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`
- Never mention Claude, AI, or LLM in commit messages
- After code changes, provide a one-liner commit message to copy/paste
- Branch prefixes: `feat/`, `fix/`, `ref/`, `docs/`
- PRs: read `.github/PULL_REQUEST_TEMPLATE.md` and follow exactly (including emoji prefixes); assign `@me`; label from: `bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`
- Update `## Unreleased` in `CHANGELOG.md` for user-facing changes

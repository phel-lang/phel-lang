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

## Testing

```bash
composer test              # All tests (quality + compiler + core)
composer test-quality      # Static analysis: cs-fixer, psalm, phpstan, rector
composer test-compiler     # PHPUnit unit + integration tests
composer test-core         # Phel core tests (./bin/phel test)
composer fix               # Auto-fix: rector + cs-fixer
```

## Git

- Conventional commits: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`
- Never mention Claude, AI, or LLM in commit messages
- After code changes, provide a one-liner commit message to copy/paste
- Branch prefixes: `feat/`, `fix/`, `ref/`, `docs/`
- PRs: read `.github/PULL_REQUEST_TEMPLATE.md` and follow exactly (including emoji prefixes); assign `@me`; label from: `bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`
- Update `## Unreleased` in `CHANGELOG.md` for user-facing changes

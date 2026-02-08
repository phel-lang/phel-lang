# Phel Language

Phel is a functional programming language that compiles to PHP. It is a dialect of Lisp inspired by Clojure and Janet.

- Website: https://phel-lang.org
- Repository: https://github.com/phel-lang/phel-lang
- License: MIT
- Requires: PHP >= 8.3

## Architecture

```
src/php/       → Compiler, runtime, CLI (PHP, PSR-4: Phel\)
src/phel/      → Core library (Phel source: core, str, html, http, json, test)
tests/php/     → PHPUnit tests (unit + integration)
tests/phel/    → Phel test files
build/         → PHAR build scripts, release tooling
```

## Code Style

- **PHP**: PSR-12 via php-cs-fixer + rector. Run `composer fix` to auto-fix.
- **Phel**: kebab-case for functions/variables. Clojure-aligned semantics.

## Testing

```bash
composer test              # Run all tests (quality + compiler + core)
composer test-quality      # Static analysis: cs-fixer, psalm, phpstan, rector
composer test-compiler     # PHPUnit unit + integration tests
composer test-core         # Phel core tests (./bin/phel test)
composer fix               # Auto-fix: rector + cs-fixer
```

## Git Conventions

- **Commits**: conventional commits (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`)
- **Branches**: `feat/`, `fix/`, `ref/`, `docs/` prefixes
- **CHANGELOG**: update the `## Unreleased` section in CHANGELOG.md for user-facing changes
- **PRs**: follow `.github/PULL_REQUEST_TEMPLATE.md`, assign author, add labels

### GitHub Labels

`bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`

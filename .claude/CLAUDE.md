# Phel Language

Phel is a functional programming language that compiles to PHP. A Lisp dialect inspired by Clojure and Janet.

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

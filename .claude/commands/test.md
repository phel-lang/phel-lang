Run project tests.

Usage: /test [scope]

Scopes:
- `all` (default): `composer test` — full suite (quality + compiler + core)
- `quality`: `composer test-quality` — static analysis (cs-fixer, psalm, phpstan, rector)
- `compiler`: `composer test-compiler` — PHPUnit unit + integration tests
- `core`: `composer test-core` — Phel core tests (./bin/phel test)
- `quick`: `composer test-compiler && composer test-core` — skip static analysis

Run the appropriate command based on $ARGUMENTS (default: all).
Report the results concisely: pass/fail counts, any errors.

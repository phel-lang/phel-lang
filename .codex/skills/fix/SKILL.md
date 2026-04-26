---
name: fix
description: Auto-fix and verify Phel code quality. Use when Codex is asked to run Rector, PHP-CS-Fixer, PHPStan, compiler tests, or clean up style/static-analysis failures.
---

# Fix Code Quality

## Workflow

1. Run project auto-fixers:
   ```bash
   composer fix
   ```

2. For a single PHP file when a narrow fix is requested:
   ```bash
   ./vendor/bin/php-cs-fixer fix "<file>"
   ```

3. Run static analysis:
   ```bash
   composer phpstan
   ```

4. Run compiler tests to verify behavior:
   ```bash
   composer test-compiler
   ```

5. Summarize what changed and any remaining failures. Include exact failing commands if something cannot be fixed immediately.

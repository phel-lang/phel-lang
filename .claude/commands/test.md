# Quick Test Runner

Run tests with smart filtering.

## Arguments
- `$ARGUMENTS` - Optional: scope or filter

## Instructions

1. If `$ARGUMENTS` is empty or `all`, run full suite:
   ```bash
   composer test
   ```

2. If `$ARGUMENTS` is a known scope:
   - `quality` → `composer test-quality`
   - `compiler` → `composer test-compiler`
   - `core` → `composer test-core`
   - `quick` → `composer test-compiler && composer test-core` (skip static analysis)

3. If `$ARGUMENTS` looks like a test class or method name:
   ```bash
   ./vendor/bin/phpunit --filter "$ARGUMENTS"
   ```

4. If `$ARGUMENTS` looks like a file path:
   ```bash
   ./vendor/bin/phpunit "$ARGUMENTS"
   # or for Phel tests:
   ./bin/phel test "$ARGUMENTS"
   ```

5. Report results clearly with pass/fail count.

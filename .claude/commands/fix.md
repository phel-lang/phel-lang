# Fix All Code Quality Issues

Auto-fix all code quality issues in sequence.

## Arguments
- `$ARGUMENTS` - Optional: file path to fix a specific file instead of the whole project

## Instructions

1. Run rector + cs-fixer:
   ```bash
   composer fix
   ```
   Or for a specific file:
   ```bash
   ./vendor/bin/php-cs-fixer fix "$ARGUMENTS"
   ```

2. Run static analysis to check for remaining issues:
   ```bash
   composer phpstan
   ```

3. Run tests to verify nothing broke:
   ```bash
   composer test-compiler
   ```

4. Summarize what was fixed and any remaining issues.

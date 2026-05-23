---
description: Auto-fix all code quality issues with rector, cs-fixer, and phpstan
argument-hint: "[file-path]"
disable-model-invocation: true
allowed-tools: "Read, Edit, Bash(composer *), Bash(./vendor/bin/*)"
---

# Fix All Code Quality Issues

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

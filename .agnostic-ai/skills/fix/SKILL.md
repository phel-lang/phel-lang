---
description: Auto-fix PHP style, then run PHPStan and compiler tests.
argument-hint: "[file-path]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Edit, Bash(composer *), Bash(./vendor/bin/*)"
x-codex:
  interface:
    display_name: Fix
---

# Fix Code Quality

1. Fix: `composer fix` for the project, or `./vendor/bin/php-cs-fixer fix "<argument>"` for one file.
2. Check: `composer phpstan`, then `composer test-compiler`.
3. Summarize what changed and what is still failing.

---
description: Fix PHP style and verify the affected scope.
argument-hint: "[file-path]"
disable-model-invocation: true
allowed-tools:
  - "read"
  - "edit"
  - "shell(composer *)"
  - "shell(./vendor/bin/*)"
---

# Fix Code Quality

1. Inspect the requested files or current diff. Run Rector and CS Fixer on those PHP paths with the repository config; use `composer fix` for an explicit project-wide request.
2. Review the tool diff for behavior changes. Select verification through `.agnostic-ai/rules/build-test-and-development-commands.md` and `.agnostic-ai/skills/test/SKILL.md`; reuse passing behavior checks when only layout or imports changed, and rerun affected checks for behavior changes.
3. Summarize the changes, checks and remaining failures.

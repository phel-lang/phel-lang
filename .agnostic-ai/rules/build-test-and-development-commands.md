---
name: build-test-and-development-commands
description: Pick checks by changed behavior, run them once, reuse passes.
---

# Verification

- Check the changed behavior and its integration boundary. Broaden only for cross-cutting changes, failures or an explicit ask. Commands per scope: `.agnostic-ai/skills/test/SKILL.md`.
- Run the gate once, after reviewing the whole diff. Reuse passing checks on unchanged code; rerun affected checks after a fix. Full CI gate: `COMPOSER_PROCESS_TIMEOUT=0 composer test`.
- Run expensive checks one at a time. Static analysis covers `src/` only, never `tests/` (ADR 0010).
- The pre-commit hook runs `composer test-commit` (quality, unit, core) when PHP or Phel files are staged; commit with `COMPOSER_PROCESS_TIMEOUT=0`. It skips the integration suite: run `composer test-compiler` when a change can affect it.
- Never bypass required hooks or claim unrun checks passed.

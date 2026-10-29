---
name: build-test-and-development-commands
description: Select verification by changed behavior and reuse passing checks.
---

# Verification

Choose tests and quality checks for the changed behavior and affected integration boundaries. Broaden for cross-cutting changes, failures or explicit requirements; do not run every suite for every change. Commands and scope selection: `.agnostic-ai/skills/test/SKILL.md`.

Run the selected gate after reviewing the complete diff. Reuse passing checks on unchanged code; rerun affected checks after fixes. Coordinate expensive checks and run static analysis sequentially, over `src/` only (ADR 0010). The full CI-matched gate is `COMPOSER_PROCESS_TIMEOUT=0 composer test` when broader coverage is needed.

Agent specs: `agnostic-ai validate`, `agnostic-ai lint --strict`, `agnostic-ai sync`, then `agnostic-ai sync --check`. Scope changes also need `agnostic-ai explain --file <path> --target <claude|codex>`. Prose needs a diff and link check; shell tooling needs `composer test-tools`; downstream `resources/agents/` needs `composer test-agents`.

If installed, the pre-commit hook (`tools/git-hooks/init.sh`) runs `composer test-all` for staged PHP or Phel. Reuse it with `COMPOSER_PROCESS_TIMEOUT=0` when triggered; otherwise run the selected gate yourself, including fixture-only changes. Never bypass required hooks or claim unrun checks passed.

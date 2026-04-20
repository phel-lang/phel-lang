# Phel agent docs

Agent-agnostic docs for AI coding assistants (Claude Code, Cursor, Codex, Gemini, Copilot, Aider) building applications **with** Phel. Compiler contributor guidance is separate: see root `AGENTS.md` and `src/php/**/CLAUDE.md`.

## Install into a user project

```bash
./vendor/bin/phel agent-install --all         # every platform
./vendor/bin/phel agent-install claude        # single platform
./vendor/bin/phel agent-install --all --with-docs   # also mirror this directory
```

Destinations: [`skills/INSTALL.md`](skills/INSTALL.md).

## Layout

| File / dir | What |
|------------|------|
| [`RULES.md`](RULES.md) | Hard rules, CLI cheatsheet, workflow. Loaded by every adapter. |
| [`index.md`](index.md) | Intent → task recipe. Route here first. |
| [`tasks/`](tasks/) | One recipe per common workflow. |
| [`skills/`](skills/) | Per-platform adapter templates. |
| [`examples/`](examples/) | Runnable projects (`todo-app`, `http-json-api`, `cli-wordcount`). |
| [`VERSION`](VERSION) | phel-lang release this doc targets. |

Metrics template at [`docs/agent-metrics.md`](../docs/agent-metrics.md).

## Sync policy

- Hand-written docs (`tasks/`, `skills/`, `RULES.md`, `index.md`): owner updates when public surface changes.
- Examples under `examples/` are validated by `composer test-agents` (`build/validate-agents.sh`): tests must stay green against the current source.
- Ground truth lives in `docs/` and `src/phel/`. This directory only routes to it.

# Phel agent docs

Agent-agnostic docs for AI assistants building apps **with** Phel. Repo-maintenance guidance lives in the root
`AGENTS.md`, `.codex/`, `.claude/`, and `src/php/**/CLAUDE.md`.

## Install

```bash
./vendor/bin/phel agent-install --all                # every platform
./vendor/bin/phel agent-install claude               # single platform
./vendor/bin/phel agent-install --all --with-docs    # also mirror this tree
```

Targets: [`skills/INSTALL.md`](skills/INSTALL.md).

## Layout

| Path | Purpose |
|------|---------|
| [`RULES.md`](RULES.md) | Rules + CLI cheatsheet. Every adapter loads this. |
| [`index.md`](index.md) | Intent → task recipe. |
| [`tasks/`](tasks/) | One recipe per workflow. |
| [`skills/`](skills/) | Per-platform adapters. |
| [`examples/`](examples/) | Runnable projects (`todo-app`, `http-json-api`, `cli-wordcount`). |
| [`VERSION`](VERSION) | phel-lang release this doc targets. |

## Sync

- Hand-written docs (`tasks/`, `skills/`, `RULES.md`, `index.md`): update when public surface changes.
- `examples/` validated by `composer test-agents`; tests must stay green.
- Ground truth: `docs/` + `src/phel/`. This tree only routes.

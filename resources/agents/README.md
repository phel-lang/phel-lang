# Phel downstream agent docs

Agent-agnostic docs for AI assistants building apps **with** Phel.

This tree is source material for `phel agent-install`. In user projects it installs as `.agents/` by default (pass
`--no-docs` to skip), so references inside adapter files intentionally point to `.agents/...`.

Repo-maintenance guidance for phel-lang itself lives in the root `AGENTS.md`, `.codex/`, `.claude/`, and `src/php/**/CLAUDE.md`.

## Install

```bash
./vendor/bin/phel agent-install --all                  # every platform + .agents/ docs
./vendor/bin/phel agent-install claude                 # single platform + .agents/ docs
./vendor/bin/phel agent-install --auto                 # only agents detected in this project
./vendor/bin/phel agent-install --all --no-docs        # skill files only, skip the docs tree
./vendor/bin/phel agent-install --all --with-examples  # also copy runnable example apps
```

Targets: [`skills/INSTALL.md`](skills/INSTALL.md).

## Layout

| Path | Purpose |
|------|---------|
| [`RULES.md`](RULES.md) | Rules, modern-feature reference (typed `defn`, `^:async`, `^:memoize`), CLI cheatsheet. Every installed adapter loads this from `.agents/RULES.md`. |
| [`index.md`](index.md) | Intent → task recipe. |
| [`tasks/`](tasks/) | One recipe per workflow (`typed-defn`, `http-app`, `cli-tool`, ...). |
| [`skills/`](skills/) | Per-platform adapters. |
| [`examples/`](examples/) | Runnable projects (`todo-app`, `http-json-api`, `cli-wordcount`). |
| [`VERSION`](VERSION) | phel-lang release this doc targets. |

## Sync

- Hand-written docs (`tasks/`, `skills/`, `RULES.md`, `index.md`): update when public surface changes.
- `examples/` validated by `composer test-agents`; tests must stay green.
- Ground truth: `docs/` + `src/phel/`. This tree only routes.

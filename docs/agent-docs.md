# AI coding agents

`resources/agents/` ships docs, task recipes, and skill files so AI coding tools (Claude Code, Cursor, Codex, Gemini, Copilot, Aider) build Phel apps without scraping the website on cold start.

## Install

```bash
composer require phel-lang/phel-lang
./vendor/bin/phel agent-install --all                  # every platform + .agents/ docs
./vendor/bin/phel agent-install claude                 # single platform + .agents/ docs
./vendor/bin/phel agent-install --auto                 # only agents detected in this project
./vendor/bin/phel agent-install claude --no-docs       # skip the .agents/ docs tree
./vendor/bin/phel agent-install --all --with-examples  # also copy runnable example apps
```

`.agents/` (rules, task recipes, quick-syntax) is copied by default; pass `--no-docs` to skip it. Example apps are
**excluded** by default to keep installs slim — add `--with-examples` to include `.agents/examples/`.

Platforms: `claude`, `cursor`, `codex`, `gemini`, `copilot`, `aider`. Omit the platform and use `--auto` to install
only for agents already present in the project (`.claude/`, `.cursor/`, `AGENTS.md`, ...).

Existing targets back up to `<path>.pre-phel.bak`. `--force` skips backup, `--dry-run` previews,
`--uninstall` removes a skill (restoring any backup). Inspect state with `--list`; `--check` reports version drift
and exits non-zero if any.

## Destinations

| Platform | File written |
|----------|---------------|
| Claude Code | `.claude/skills/phel-lang/SKILL.md` |
| Cursor | `.cursor/rules/phel.mdc` |
| Codex / generic `AGENTS.md` | `AGENTS.md` |
| Gemini CLI | `GEMINI.md` |
| GitHub Copilot | `.github/copilot-instructions.md` |
| Aider | `CONVENTIONS.md` |

Each installed file routes the agent to `.agents/index.md` for task recipes: scaffolding, HTTP apps, CLI tools, tests, REPL, debugging, core library, PHP interop.

## Examples

`resources/agents/examples/` ships three projects (installed only with `--with-examples`):

- `todo-app/`: HTTP CRUD on `phel.router`, atom store, tests
- `http-json-api/`: three JSON endpoints
- `cli-wordcount/`: stdin + argv, PHP shim binary

## Sync

`resources/agents/VERSION` tracks the targeted phel-lang release. `composer test-agents` runs every example's tests against the current source; breaking a public API surfaces as a red build on PR.

## Repository maintenance adapters

The repo also contains AI tool config for maintaining phel-lang itself. Keep these separate from the downstream `resources/agents/` package:

| Path | Audience |
|------|----------|
| `AGENTS.md` | Shared repository policy for Codex, Aider, and generic AGENTS.md-aware tools. |
| `.codex/` | Codex-native config, hooks, exec rules, and custom subagents. |
| `.claude/` | Claude Code-native settings, hooks, skills, agents, and scoped rules. |
| `resources/agents/` | Assets shipped to users building their own Phel projects. |

## Feedback

Cold-start metrics and transcripts of agents building real apps surface gaps fastest. Open an issue with a failing transcript.

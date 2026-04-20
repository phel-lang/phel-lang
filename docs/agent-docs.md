# AI coding agents

The `.agents/` directory at the repo root ships docs, task recipes, and skill files so AI coding tools (Claude Code, Cursor, Codex, Gemini, Copilot, Aider) can build apps with Phel without scraping the website on every cold start.

## Install into your project

```bash
composer require phel-lang/phel-lang
./vendor/bin/phel agent-install --all            # every platform
./vendor/bin/phel agent-install claude           # single platform
./vendor/bin/phel agent-install --all --with-docs  # also copies the .agents/ tree
```

Platforms covered: `claude`, `cursor`, `codex`, `gemini`, `copilot`, `aider`.

The command backs up any existing target file to `<path>.pre-phel.bak` before overwriting. Pass `--force` to skip the backup, `--dry-run` to preview.

## What lands in your project

| Platform | File written |
|----------|---------------|
| Claude Code | `.claude/skills/phel-lang/SKILL.md` |
| Cursor | `.cursor/rules/phel.mdc` |
| Codex / generic `AGENTS.md` | `AGENTS.md` |
| Gemini CLI | `GEMINI.md` |
| GitHub Copilot | `.github/copilot-instructions.md` |
| Aider | `CONVENTIONS.md` |

Each file routes the agent to `.agents/index.md` (copied via `--with-docs` or linked back to the installed package) for task-based recipes: scaffolding, HTTP apps, CLI tools, testing, REPL workflow, debugging, core library, and PHP interop.

## Runnable examples

`.agents/examples/` ships three complete projects:

- `todo-app/` — HTTP CRUD over `phel\router`, in-memory atom store, tests
- `http-json-api/` — three JSON endpoints, smallest web example
- `cli-wordcount/` — CLI with stdin and file args

Agents can read or copy these directly.

## Keeping docs in sync

`.agents/VERSION` tracks the phel-lang release the docs target. `composer test-agents` runs every example's test suite against the current source, so breaking a public API surfaces as a red build on PR.

## Feedback

Cold-start metrics and transcripts of the agent building real apps are the fastest way to find gaps. If you have recipes that fail for your agent of choice, open an issue with the transcript attached.

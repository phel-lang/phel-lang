# Phel Agent Docs

Agent-agnostic entrypoint for AI coding assistants (Claude Code, Cursor, Codex, Gemini, Copilot, Aider) building apps **with** Phel.

## Audience

Developers using Phel to write applications, not contributors to the Phel compiler. Compiler contributor guidance lives in the repo root `AGENTS.md` and `src/php/**/CLAUDE.md`.

## What agents should read

Start at [`index.md`](index.md) — curated reading order by user intent.

Task recipes live in [`tasks/`](tasks/). Each recipe is short and action-oriented: inputs, steps, expected output, pitfalls.

Platform-specific skill files live in [`skills/<platform>/`](skills/).

## Ground truth

`.agents/` does **not** duplicate user docs. It points to them:

| Topic | Source |
|-------|--------|
| Syntax basics, scaffolding | `docs/quickstart.md` |
| Idiomatic patterns | `docs/patterns.md` |
| PHP interop | `docs/php-interop.md` |
| Framework integration (Symfony/Laravel) | `docs/framework-integration.md` |
| Working examples | `docs/examples/*.phel` |
| Core library docs | `(doc <fn-name>)` in REPL, `phel doc <fn>` CLI |

## Sync policy

`.agents/` evolves with the language:

- **Hand-written** (`tasks/`, `skills/`, `README.md`, `index.md`): owner updates when public surface changes. PR template checklist enforces review.
- **Generated** (`reference/`, future phase): auto-built from `:doc`/`:example` metadata of exported fns. Never edited by hand.
- **CI** (future phase): every fenced ```phel``` block in `.agents/**.md` compiles, else fails.

## Audience-matched tone

Agents consume this directory. Write fragments, tables, code blocks. Skip narrative prose.

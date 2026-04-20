# Cold-start metrics

Template for tracking how long each agent takes to build a representative app from a cold session, with and without the `.agents/` skill installed.

## Scenario

Fresh project, fresh chat session. Prompt: **"Build a todo HTTP API in Phel with tests."**

Target:
- HTTP endpoint for create/list/show/delete
- Tests pass via `./vendor/bin/phel test`
- Total wall time from first prompt to green tests

## Table

| Agent | Skill installed? | Wall time | Notes |
|-------|------------------|-----------|-------|
| Claude Code | no | | |
| Claude Code | yes | | |
| Cursor | no | | |
| Cursor | yes | | |
| Codex | no | | |
| Codex | yes | | |
| Gemini CLI | no | | |
| Gemini CLI | yes | | |
| Copilot | no | | |
| Copilot | yes | | |
| Aider | no | | |
| Aider | yes | | |

## How to contribute a run

1. Pick an agent and start a fresh session in an empty directory.
2. Run `composer require phel-lang/phel-lang`.
3. For "yes" rows only: `./vendor/bin/phel agent-install <platform>`.
4. Feed the prompt above. Time from send to green `phel test`.
5. Open a PR updating this table with your result and attach the transcript as a gist or comment link.

## Why this matters

The baseline (pre-`.agents/`) for a cold run was around 23 minutes for Claude Code building a todo app, most of it spent scraping phel-lang.org. The goal of `.agents/` is to drive that well under 5 minutes for any supported agent.

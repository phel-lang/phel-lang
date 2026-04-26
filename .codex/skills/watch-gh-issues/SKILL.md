---
name: watch-gh-issues
description: Start or explain the autonomous GitHub issue watcher for Phel. Use when Codex is asked to watch all GitHub issues, process issues one by one, keep polling every 15 minutes when idle, or invoke "$watch-gh-issues".
---

# Watch GitHub Issues

## Purpose

Use this skill to start the repo-local issue watcher that repeatedly invokes `$gh-issue`.

`$watch-gh-issues` is a Codex skill prompt, not a shell command. When the user asks to run it, execute the watcher script from the repository root.

## Default Command

```bash
.codex/skills/gh-issue/scripts/watch-gh-issues.sh --repo . --interval 900 --execute
```

## Behavior

- Poll open GitHub issues.
- Invoke Codex on the next issue with `$gh-issue`.
- Let `$gh-issue` fetch issue body and comments, assign the author when possible, branch from fresh `main`, implement with TDD, create grouped commits, open a PR, make CI green, merge when allowed, and update local `main`.
- After one issue run completes, immediately sync `main` and poll again.
- Sleep for `--interval` only when no open issue is available.
- Stop if Codex issue processing fails, so the failure can be inspected instead of retried blindly.

## Useful Modes

Dry-run one poll:

```bash
.codex/skills/gh-issue/scripts/watch-gh-issues.sh --repo . --once
```

Run one real issue and stop:

```bash
.codex/skills/gh-issue/scripts/watch-gh-issues.sh --repo . --once --execute
```

Run continuously:

```bash
.codex/skills/gh-issue/scripts/watch-gh-issues.sh --repo . --interval 900 --execute
```

Override the idle polling interval:

```bash
.codex/skills/gh-issue/scripts/watch-gh-issues.sh --repo . --interval 300 --execute
```

## Preconditions

- `gh` is authenticated with permission to read issues, create PRs, and merge when policy allows it.
- `codex` is available on `PATH`.
- The worktree is clean before the watcher starts.
- Required human review or branch protection may still stop automatic merging.

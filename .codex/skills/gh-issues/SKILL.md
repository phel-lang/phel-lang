---
name: gh-issues
description: Start or explain the autonomous GitHub issues watcher for Phel. Use when Codex is asked to watch all GitHub issues, process issues one by one, keep polling every 15 minutes when idle, or invoke "$gh-issues".
---

# GitHub Issues Watcher

## Purpose

Use this skill to start the repo-local issues watcher that repeatedly invokes `$gh-issue`.

`$gh-issues` is a Codex skill prompt, not a shell command. When the user asks to run it, execute the watcher script from the repository root.

## Default Command

```bash
.codex/skills/gh-issue/scripts/gh-issues.sh --repo . --interval 900 --execute
```

## Behavior

- Poll open GitHub issues.
- Invoke Codex on the next issue with `$gh-issue`.
- Let `$gh-issue` fetch issue body and comments, assign the authenticated `gh` user running the script when possible, branch from fresh `main`, implement with TDD, create grouped commits, run a final refactor pass over every touched file as a separate `ref(...)` commit, open a PR, make CI green, merge when allowed, and update local `main`.
- In watcher mode, use focused local tests during implementation and GitHub CI as the full quality gate. Commits are created with `git commit --no-verify` to avoid the nested Codex process entering the local commit-time PHPUnit gate.
- After one issue run completes, immediately sync `main` and poll again.
- Sleep for `--interval` only when no open issue is available.
- Stop if Codex issue processing fails, so the failure can be inspected instead of retried blindly.

## Useful Modes

Dry-run one poll:

```bash
.codex/skills/gh-issue/scripts/gh-issues.sh --repo . --once
```

Run one real issue and stop:

```bash
.codex/skills/gh-issue/scripts/gh-issues.sh --repo . --once --execute
```

Run continuously:

```bash
.codex/skills/gh-issue/scripts/gh-issues.sh --repo . --interval 900 --execute
```

Override the idle polling interval:

```bash
.codex/skills/gh-issue/scripts/gh-issues.sh --repo . --interval 300 --execute
```

## Preconditions

- `gh` is authenticated with permission to read issues, create PRs, and merge when policy allows it.
- `codex` is available on `PATH`.
- The worktree is clean before the watcher starts.
- Required human review or branch protection may still stop automatic merging unless the authenticated user can use an approved admin bypass.

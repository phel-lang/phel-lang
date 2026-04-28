---
description: Walk over all unassigned GitHub issues and process each one via the /gh-issue skill, sequentially.
argument-hint: "[--limit N] [--label foo] [--dry-run]"
disable-model-invocation: true
allowed-tools: "Read, Bash(gh *), Bash(git *), Bash(composer *), Skill(gh-issue), Skill(pr)"
---

# GitHub Issues Watcher

## Purpose

Process every open, **unassigned** GitHub issue in this repo, one after another, by delegating each to the `/gh-issue` skill. Stop on first hard failure so it can be inspected.

This is the Claude-side counterpart to `.codex/skills/gh-issues/SKILL.md` — but driven from inside the Claude session rather than a polling shell script.

## Args

- `--limit N` — process at most N issues this run (default: all).
- `--label foo` — only issues carrying label `foo`.
- `--dry-run` — list issues that would be processed; do not invoke `/gh-issue`.

Strip leading `#` if user passes `#123` style.

## Phase 1: Discover

Fetch open, unassigned issues, oldest first:

```bash
gh issue list \
  --state open \
  --search "no:assignee" \
  --json number,title,labels,createdAt \
  --limit 200
```

Filters:
- Drop issues with any assignee (defensive — `no:assignee` should already exclude).
- Apply `--label` filter if given.
- Apply `--limit` if given.
- Sort ascending by `createdAt` (FIFO).

Print the queue: `#<num> <title>` per line. If empty, exit cleanly.

## Phase 2: Worktree Sanity

Before touching any issue:

```bash
git status --porcelain
git fetch origin main
git checkout main && git reset --hard origin/main
```

Abort if worktree dirty. Never auto-stash.

## Phase 3: Process Loop

For each issue in the queue:

1. Re-check it is still unassigned (someone may have grabbed it):
   ```bash
   gh issue view <num> --json assignees -q '.assignees | length'
   ```
   If `> 0`, skip.

2. Invoke the `/gh-issue` skill with the issue number. That skill owns:
   - self-assign via `gh issue edit <num> --add-assignee @me`
   - branch from fresh `main` (prefix from labels: `fix/`, `feat/`, `docs/`)
   - TDD implementation
   - `composer test` green locally
   - changelog entry under `## Unreleased`
   - commit with `Related to #<num>`
   - **mandatory final refactor pass** over every touched file (separate `ref(...)` commit) before opening the PR
   - PR opened via `/pr #<num>`

3. After `/gh-issue` returns, wait for CI green on the PR:
   ```bash
   gh pr checks --watch
   ```
   Fix red checks on the branch before moving on.

4. Merge when allowed:
   ```bash
   gh pr merge --auto --squash --admin
   ```

5. Sync `main` for next iteration:
   ```bash
   git checkout main && git fetch origin main && git reset --hard origin/main
   ```

6. Continue with next issue.

## Stop Conditions

Halt the loop and surface the failure when:

- `/gh-issue` errors out or leaves the worktree dirty.
- `composer test` fails after implementation.
- CI stays red after one fix attempt.
- Merge is blocked by branch protection beyond `--admin` bypass.
- `--limit` reached.
- Queue empty.

Do **not** retry blindly. Report which issue failed and why.

## Dry Run

With `--dry-run`, only execute Phase 1 and print the queue. No assignment, no branching, no commits.

## Preconditions

- `gh` authenticated, can read issues, open and merge PRs.
- Worktree clean.
- `main` exists and tracks `origin/main`.
- `/gh-issue` and `/pr` skills available in this session.

## Notes

- Commits inside `/gh-issue` should use `git commit --no-verify` (project rule: 6 env-failing tests block the local pre-commit hook).
- Treat GitHub CI as the full quality gate; locally run focused tests during implementation, full `composer test` once before commit.
- Never split bundled changes into multiple PRs unless the issue explicitly demands it.

---
name: gh-issue
description: End-to-end GitHub issue execution workflow. Use when Codex is given a GitHub issue number, #number, issue URL, or asked to fetch an issue, create a branch, implement it with TDD, commit grouped changes, open a PR, watch/fix CI, merge, update main, or continue processing available issues.
---

# GitHub Issue Workflow

## Quick Start

Run the helper from the repository root:

```bash
.codex/skills/gh-issue/scripts/prepare-gh-issue.sh <issue-number-or-url> --setup
```

If this skill is installed in the user Codex skill directory, the same helper is available at:

```bash
~/.codex/skills/gh-issue/scripts/prepare-gh-issue.sh <issue-number-or-url> --setup
```

The script fetches the issue title, body, author, labels, assignees, state, and all comments into `.git/codex-gh-issues/issue-<number>.md`. With `--setup`, it also attempts to assign the authenticated `gh` user running the script and creates a branch from fresh `main`.

If the script fails because the worktree is dirty, inspect `git status --short`. Do not stash, reset, or discard changes without explicit user approval unless every change is known to be yours.

## Execution Contract

Treat the issue body and every comment as requirements context. Prefer later maintainer comments over older ambiguous text when they conflict. If requirements are contradictory, unsafe, or require product judgment that cannot be inferred from project policy, stop and ask the user before editing.

Use repository instructions as the authority for local practice. In the Phel repository, read `AGENTS.md` and any touched `src/php/<Module>/CLAUDE.md` before editing that module.

## Workflow

1. Parse and fetch the issue with `prepare-gh-issue.sh`.
2. Read the generated context file completely.
3. Confirm the issue is open. If it is closed, ask before continuing.
4. Create an initial plan:
   - issue goal in one sentence
   - affected areas and files to inspect
   - TDD test plan
   - implementation order
   - long-term design consideration, including why the approach fits project direction
5. Execute the plan without waiting for approval unless there is a real blocker or non-logical human decision.
6. Follow TDD:
   - write or update failing focused tests first
   - implement the minimum correct behavior
   - keep tests green as scope expands
7. Commit by context as work lands. Use precise staged files, not blanket `git add .`, unless the status is fully understood.
8. After the issue plan is complete, perform one explicit refactor pass over only the branch changes. Commit that pass separately when it produces a real diff. If no refactor is justified, mention that in the PR body or final summary instead of creating a ceremonial commit.
9. Run the repository quality gate. For Phel, prefer `composer test`; use narrower tests first while iterating.
10. Update changelog only for user-facing changes, following repository policy.
11. Open a PR with `gh pr create`, following the repository PR template exactly when present.
12. Watch CI with `gh pr checks --watch`. Fix failures until all required checks are green.
13. Merge the PR when CI is green and policy allows it. Update local `main` with `git switch main && git pull --ff-only --prune`.

## Branch and Commit Rules

Branch from fresh `main`. Use the prefix inferred from labels:

- `bug` -> `fix/`
- `enhancement` or `feature` -> `feat/`
- `documentation` or `docs` -> `docs/`
- `performance` or `perf` -> `perf/`
- `refactor` -> `ref/`
- `test` or `tests` -> `test/`
- otherwise -> `feat/`

Branch format: `<prefix><issue-number>-<slug>`.

Use conventional commit prefixes already present in the repository. Include `Related to #<issue-number>` or `Closes #<issue-number>` as appropriate. Use `Closes` only when the PR fully resolves the issue.

## Multiple Issues and Watching

A Codex skill only runs when a Codex session is active; it is not a persistent daemon by itself. To continue through available issues in one active session:

```bash
gh issue list --state open --limit 20 --json number,title,labels,assignees,author
```

Pick the next actionable unblocked issue and repeat the workflow. For a true 15-minute background poller, use an external scheduler such as `launchd`, cron, GitHub Actions, or a small local supervisor that invokes the Codex CLI with this skill prompt.

This skill includes an optional local supervisor:

```bash
.codex/skills/gh-issue/scripts/gh-issues.sh --repo /path/to/repo --interval 900 --execute
```

Without `--execute`, the watcher only prints the issue it would process. Use `--once` to process or print a single candidate and exit. The interval is only an idle delay: after Codex finishes an issue run, the watcher syncs `main` and immediately checks for the next open issue. It sleeps only when no issue is available.

## Human Escalation

Ask before continuing when:

- GitHub authentication, assignment, branch setup, CI, or merge permissions fail.
- The worktree contains changes that may belong to the user.
- The issue requires a product/API decision not derivable from code, comments, or repo policy.
- The requested behavior conflicts with long-term maintainability, security, or compatibility.
- CI is failing for reasons unrelated to the branch and the next action would affect shared state.

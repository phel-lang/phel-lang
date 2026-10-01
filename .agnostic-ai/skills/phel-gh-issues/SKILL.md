---
description: Work through my open GitHub issues one by one.
argument-hint: "[--limit N] [--label foo] [--dry-run]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Bash(gh *), Bash(git *), Bash(composer *), Skill(gh-issue), Skill(pr)"
x-codex:
  interface:
    display_name: GitHub Issues
---

# GitHub Issues: phel-lang specifics

Only what this repository adds to the generic queue workflow (clean tree, queue of issues unassigned or assigned to me, oldest first, one at a time). Where they differ, this file wins.

- Queue: `gh issue list --state open --search "no:assignee"` plus `gh issue list --state open --assignee "@me"`, merged by number. Re-check each issue's assignees right before starting it; skip it if someone else took it.
- Start from a clean `main` that matches `origin/main`. Never stash.
- Work each issue with the `gh-issue` skill. It merges its own PR (see its Merge section), so the next issue starts only after the previous PR merged and `main` is synced.
- Stop the run when `/gh-issue` fails or leaves the tree dirty, when CI stays red after one fix attempt, or when the `--admin` merge is refused.
- One PR per issue. Do not split an issue across PRs unless it asks for that.

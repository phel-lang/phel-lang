---
description: phel-lang rules read by /gh-issues.
disable-model-invocation: true
---

# GitHub Issues: phel-lang specifics

The `gh-issues` skill reads this file. It holds this repository's queue rules; where they differ from the generic skill, this file wins.

- Queue unassigned issues and those assigned to `@me`. Follow the user's priority and concurrency; otherwise use oldest first, one at a time. Re-check assignees before starting; skip issues taken by someone else.
- Branch each issue from current `origin/main`, using separate worktrees for authorized parallel work. Never stash another change or reuse its dirty tree. Start dependent work only after its prerequisite PR merged.
- Work each issue with `gh-issue` and `.agnostic-ai/skills/phel-gh-issue/SKILL.md`. Keep file ownership and validation budgets explicit; coordinate expensive checks and never overlap typechecks.
- Investigate failed checks and rerun affected checks after fixes. Keep unresolved issues open, preserve their branches and continue independent authorized work. Never merge past a failing required check.
- Follow the single-issue skill's merge, sync and cleanup steps. A queued auto-merge is pending until GitHub reports `MERGED`.
- One PR per issue. Do not split an issue across PRs unless it asks for that.

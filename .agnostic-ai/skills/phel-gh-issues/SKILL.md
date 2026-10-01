---
description: phel-lang rules read by /gh-issues.
disable-model-invocation: true
x-codex:
  interface:
    display_name: phel-lang issue queue rules
---

# GitHub Issues: phel-lang specifics

The `gh-issues` skill reads this file. It holds only what this repository adds to the generic queue workflow (clean tree, queue of issues unassigned or assigned to me, oldest first, one at a time). Where they differ, this file wins.

- Queue: `gh issue list --state open --search "no:assignee"` plus `gh issue list --state open --assignee "@me"`, merged by number. Re-check each issue's assignees right before starting it; skip it if someone else took it.
- Start from a clean `main` that matches `origin/main`. Never stash.
- Work each issue with the `gh-issue` skill, which follows `.agnostic-ai/skills/phel-gh-issue/SKILL.md`. It merges its own PR (see the Merge section there), so the next issue starts only after the previous PR merged and `main` is synced.
- Stop the run when `/gh-issue` fails or leaves the tree dirty, when CI stays red after one fix attempt, or when the `--admin` merge is refused.
- One PR per issue. Do not split an issue across PRs unless it asks for that.

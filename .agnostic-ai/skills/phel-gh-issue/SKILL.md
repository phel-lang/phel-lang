---
description: phel-lang rules read by /gh-issue.
disable-model-invocation: true
---

# GitHub Issue: phel-lang specifics

The `gh-issue` skill reads this file. It holds only what this repository adds to the generic issue workflow (read the issue and every comment, assign, branch, test first, verify each criterion, open the PR). Where they differ, this file wins.

## Branch

Prefix from the issue's label: `bug` gives `fix/`, `enhancement` gives `feat/`, `documentation` gives `docs/`, anything else `feat/`. Name: `<prefix><issue-number>-<slug>`, from a fresh `origin/main`.

## Verify

Before the final gate, reread the diff for duplication, dead code, naming drift and module boundary violations. Make any needed fixes as part of the change; do not create an extra refactor commit without a concrete improvement. Verify each acceptance criterion and run the applicable gate once, per `.agnostic-ai/rules/build-test-and-development-commands.md`. Fix every failure before shipping.

## Ship

1. Add or merge the `CHANGELOG.md` entry per `.agnostic-ai/rules/workflow.md`.
2. Commit with a conventional message (`.agnostic-ai/rules/workflow.md`) and a body line `Related to #<issue-number>`.
3. Open the PR with `/pr #<issue-number>`: it owns the review, template, title and label. Reuse checks that already passed for this change set.

## Merge

1. Wait for CI: `gh pr checks <pr> --watch`. Push fixes until every required check is green.
2. Merge: `gh pr merge <pr> --squash --admin --delete-branch`. If `--admin` is refused, use `--auto --squash --delete-branch`. Never merge past a failing required check. Query `gh pr view <pr> --json state,mergedAt` after either command. Continue only when the state is `MERGED`; otherwise report that auto-merge is pending and keep the branch and worktree.
3. Sync from the main checkout, found with `git worktree list`. Fetch with `git fetch --prune origin`. Check its working tree is clean and local `main` has no unpushed commits, then update it with `git merge --ff-only origin/main`. Preserve local changes or divergent commits and report why the update was skipped.
4. Clean up only after confirming the PR merged and its worktree is clean, with no unpushed commits. Run `git worktree remove <path>` from another checkout, then `~/.agnostic-ai/scripts/reap-stale-agents.sh`. Delete the local PR branch with `git branch -D <branch>` only after those checks (squash merges do not satisfy `-d`). Delete the remote branch with `git push origin --delete <branch>` if it survived and has no newer commits than the merged PR head. Finish with `git fetch --prune origin` and `git worktree prune`. Keep anything with local changes, unpushed commits or an open PR, and report it.

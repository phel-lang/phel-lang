---
description: Take one GitHub issue from branch to merged PR.
argument-hint: "[issue-number]"
# Model-invocable on purpose: /gh-issue calls /pr, and /gh-issues calls /gh-issue.
x-codex:
  interface:
    display_name: GitHub Issue
---

# GitHub Issue: phel-lang specifics

Only what this repository adds to the generic issue workflow (read the issue and every comment, assign, branch, test first, verify each criterion, open the PR). Where they differ, this file wins.

!`gh issue view ${ARGUMENTS#\#} --json number,url,title,body,labels,assignees,state,comments 2>/dev/null || echo "Provide an issue number"`

## Branch

Prefix from the issue's label: `bug` gives `fix/`, `enhancement` gives `feat/`, `documentation` gives `docs/`, anything else `feat/`. Name: `<prefix><issue-number>-<slug>`, from a fresh `origin/main`.

## Verify

Run focused tests while working, then `COMPOSER_PROCESS_TIMEOUT=0 composer test` once, per `.agnostic-ai/rules/build-test-and-development-commands.md`. Fix every failure before shipping.

## Ship

1. Add or merge the `CHANGELOG.md` entry per `.agnostic-ai/rules/workflow.md`.
2. Commit with a conventional message (`.agnostic-ai/rules/workflow.md`) and a body line `Related to #<issue-number>`.
3. Final refactor commit, mandatory and last before the PR: reread every touched file for duplication, dead code, naming drift, speculative guards and violations of `.agnostic-ai/rules/php.md`, `modules.md` or `compiler.md`. Fix, rerun `composer test`, and commit as `ref(<scope>): polish <area> after #<issue-number>`. If there is nothing to fix, say so in the PR body.
4. Open the PR with `/pr #<issue-number>`: it owns the template, title and label.

## Merge

1. Wait for CI: `gh pr checks <pr> --watch`. Push fixes until every required check is green.
2. Merge: `gh pr merge <pr> --squash --admin --delete-branch`. If `--admin` is refused, use `--auto --squash --delete-branch` and report that the PR waits for a human. Never merge past a failing required check.
3. Sync: `git checkout main && git fetch origin main && git reset --hard origin/main`.

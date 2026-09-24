#!/bin/bash
# phel-lang git hook (installed by tools/git-hooks/init.sh)

# Shared by post-merge, post-checkout and post-rewrite. init.sh copies it into
# .git/hooks/ next to them, so a checked-out branch cannot change what runs.
#
# Re-emits the gitignored agent config (.claude/, .codex/, .agents/,
# AGENTS.md, CLAUDE.md) when HEAD's specs differ from the ones last synced in
# this worktree, so a session does not keep loading rules and skills from
# before a pull, a rebase or a branch switch. It compares against its own
# record, not ORIG_HEAD, which a rebase can overwrite.
#
# Trust: sync emits hooks the next agent session runs, so it happens on its
# own only when the specs match origin/main (the reviewed ones) and have no
# local changes. Otherwise it prints how to sync by hand. An ignored local
# layer (agnostic-ai.local.yaml, .agnostic-ai.local/) is the developer's own
# config and trusted like ~/.gitconfig; a branch that tracks one is not.
# A hook never fails the git command that ran it.

command -v agnostic-ai >/dev/null 2>&1 || exit 0

state="$(git rev-parse --git-path agnostic-ai-synced)"
specs="$(git rev-parse -q --verify HEAD:.agnostic-ai 2>/dev/null) $(git rev-parse -q --verify HEAD:agnostic-ai.yaml 2>/dev/null)"
[ "$(cat "$state" 2>/dev/null)" = "$specs" ] && exit 0

inputs=(.agnostic-ai agnostic-ai.yaml agnostic-ai.local.yaml .agnostic-ai.local)
if git rev-parse -q --verify origin/main >/dev/null \
  && git diff --quiet origin/main HEAD -- "${inputs[@]}" 2>/dev/null \
  && [ -z "$(git status --porcelain --untracked-files=all -- "${inputs[@]}" 2>/dev/null)" ]; then
  echo "agnostic-ai specs changed: running agnostic-ai sync"
  if agnostic-ai sync -q; then
    printf '%s' "$specs" > "$state"
  else
    echo "agnostic-ai sync failed; run it by hand" >&2
  fi
else
  echo "agnostic-ai specs differ from origin/main or have local changes: not syncing. Review them, then run agnostic-ai sync." >&2
fi

exit 0

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
# own only when the specs are reviewed (on origin/main, at its tip or where the
# branch forked) and have no local changes. Otherwise it prints how to sync by hand. An ignored local
# layer (agnostic-ai.local.yaml, .agnostic-ai.local/) is the developer's own
# config and trusted like ~/.gitconfig; a branch that tracks one is not.
# A hook never fails the git command that ran it.

command -v agnostic-ai >/dev/null 2>&1 || exit 0
[ -f "$(git rev-parse --show-toplevel 2>/dev/null)/agnostic-ai.yaml" ] || exit 0

inputs=(.agnostic-ai agnostic-ai.yaml agnostic-ai.local.yaml .agnostic-ai.local)
state="$(git rev-parse --git-path agnostic-ai-synced)"
# Fingerprint of every tracked input at HEAD (a missing one hashes to empty).
specs=""
for input in "${inputs[@]}"; do
  specs="$specs $(git rev-parse -q --verify "HEAD:$input" 2>/dev/null)"
done
[ "$(cat "$state" 2>/dev/null)" = "$specs" ] && exit 0
# Other specs are checked out, so the record no longer describes the output:
# a manual sync on this branch, or a sync that fails halfway, may change it.
# Forget it now; only a successful sync below writes it again.
rm -f "$state"

# Reviewed means HEAD's inputs are on origin/main: equal to its tip, or
# untouched since the branch forked from it (an older reviewed version).
reviewed() {
  git rev-parse -q --verify origin/main >/dev/null || return 1
  git diff --quiet origin/main HEAD -- "${inputs[@]}" 2>/dev/null && return 0
  local base
  base="$(git merge-base origin/main HEAD 2>/dev/null)" || return 1
  git diff --quiet "$base" HEAD -- "${inputs[@]}" 2>/dev/null
}

if reviewed \
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

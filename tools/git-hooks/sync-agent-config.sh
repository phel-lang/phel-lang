#!/bin/bash

# Shared by post-merge, post-checkout and post-rewrite. init.sh copies it into
# .git/hooks/ next to them, so a checked-out branch cannot change what runs.
# Usage: sync-agent-config.sh <from-ref> <to-ref>
#
# Re-emits the gitignored agent config (.claude/, .codex/, .agents/,
# AGENTS.md, CLAUDE.md) when the specs changed between the two refs, so a
# session does not keep loading the rules and skills from before the pull.
#
# Trust: sync emits hooks the next agent session runs. It happens on its own
# only when the specs match origin/main, the reviewed ones. On any other
# branch it prints how to sync by hand. A hook never fails the git command.

command -v agnostic-ai >/dev/null 2>&1 || exit 0
[ -n "${1:-}" ] && [ -n "${2:-}" ] || exit 0

specs='^(\.agnostic-ai/|agnostic-ai\.yaml$)'
git diff --name-only "$1" "$2" 2>/dev/null | grep -Eq "$specs" || exit 0

if git rev-parse --verify -q origin/main >/dev/null \
  && git diff --quiet origin/main HEAD -- .agnostic-ai agnostic-ai.yaml 2>/dev/null; then
  echo "agnostic-ai specs changed: running agnostic-ai sync"
  agnostic-ai sync -q || echo "agnostic-ai sync failed; run it by hand" >&2
else
  echo "agnostic-ai specs differ from origin/main: not syncing. Review them, then run agnostic-ai sync." >&2
fi

exit 0

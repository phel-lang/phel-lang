#!/bin/bash

# Shared by post-merge, post-checkout and post-rewrite.
# Usage: sync-agent-config.sh <from-ref> <to-ref>
#
# Re-emits the gitignored agent config (.claude/, .codex/, .agents/,
# AGENTS.md, CLAUDE.md) when the specs differ between the two refs. Without
# it, a session keeps loading the rules and skills from before the pull.
# A hook must never fail the git operation that ran it, so this always exits 0.

command -v agnostic-ai >/dev/null 2>&1 || exit 0
[ -n "${1:-}" ] && [ -n "${2:-}" ] || exit 0

if git diff --name-only "$1" "$2" 2>/dev/null | grep -Eq '^(\.agnostic-ai/|agnostic-ai\.yaml$)'; then
  echo "agnostic-ai specs changed: running agnostic-ai sync"
  agnostic-ai sync -q || echo "agnostic-ai sync failed; run it by hand" >&2
fi

exit 0

#!/bin/bash

# Re-emit the gitignored agent config (.claude/, .codex/, .agents/, AGENTS.md,
# CLAUDE.md) when a pull brought in spec changes. Without this, a session keeps
# loading the rules and skills from before the pull.

set -e

command -v agnostic-ai >/dev/null 2>&1 || exit 0

if git diff --name-only ORIG_HEAD HEAD 2>/dev/null | grep -Eq '^(\.agnostic-ai/|agnostic-ai\.yaml$)'; then
  echo "agnostic-ai specs changed: running agnostic-ai sync"
  agnostic-ai sync -q || echo "agnostic-ai sync failed; run it by hand" >&2
fi

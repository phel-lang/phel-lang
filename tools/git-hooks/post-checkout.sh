#!/bin/bash

# Same as post-merge.sh, for branch switches: re-emit the gitignored agent
# config when the specs differ between the two branches.

set -e

# $3 is 1 for a branch checkout, 0 for a file checkout.
[ "${3:-0}" = "1" ] || exit 0
command -v agnostic-ai >/dev/null 2>&1 || exit 0

if git diff --name-only "$1" "$2" 2>/dev/null | grep -Eq '^(\.agnostic-ai/|agnostic-ai\.yaml$)'; then
  echo "agnostic-ai specs changed: running agnostic-ai sync"
  agnostic-ai sync -q || echo "agnostic-ai sync failed; run it by hand" >&2
fi

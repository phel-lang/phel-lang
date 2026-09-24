#!/bin/bash

# $3 is 1 for a branch switch, 0 for a file checkout.
[ "${3:-0}" = "1" ] || exit 0
# A rebase checks out its base first; post-rewrite syncs once it finishes.
[ -d "$(git rev-parse --git-path rebase-merge)" ] && exit 0
[ -d "$(git rev-parse --git-path rebase-apply)" ] && exit 0
"$(git rev-parse --show-toplevel)/tools/git-hooks/sync-agent-config.sh" "$1" "$2"

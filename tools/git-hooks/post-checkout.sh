#!/bin/bash
# phel-lang git hook (installed by tools/git-hooks/init.sh)

# $3 is 1 for a branch switch, 0 for a file checkout.
[ "${3:-0}" = "1" ] || exit 0
helper="$(dirname "$0")/phel-sync-agent-config"
# Call only the helper init.sh installed, never a file another tool put there.
grep -q '^# phel-lang git hook' "$helper" 2>/dev/null || exit 0
"$helper"

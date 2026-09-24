#!/bin/bash
# phel-lang git hook (installed by tools/git-hooks/init.sh)

# `git pull` with merge or fast-forward.
helper="$(dirname "$0")/phel-sync-agent-config"
# Call only the helper init.sh installed, never a file another tool put there.
grep -q '^# phel-lang git hook' "$helper" 2>/dev/null || exit 0
"$helper"

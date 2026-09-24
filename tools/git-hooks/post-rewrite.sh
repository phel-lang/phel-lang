#!/bin/bash
# phel-lang git hook (installed by tools/git-hooks/init.sh)

# `git pull --rebase` and `git rebase` run this hook, not post-merge.
# An amend changes nothing upstream.
[ "${1:-}" = "rebase" ] || exit 0
"$(dirname "$0")/phel-sync-agent-config"

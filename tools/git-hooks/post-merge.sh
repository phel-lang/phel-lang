#!/bin/bash
# phel-lang git hook (installed by tools/git-hooks/init.sh)

# `git pull` with merge or fast-forward.
"$(dirname "$0")/sync-agent-config"

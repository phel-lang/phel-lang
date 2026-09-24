#!/bin/bash
# phel-lang git hook (installed by tools/git-hooks/init.sh)

# `git pull` with merge or fast-forward. ORIG_HEAD is the commit before it.
"$(dirname "$0")/sync-agent-config" ORIG_HEAD HEAD

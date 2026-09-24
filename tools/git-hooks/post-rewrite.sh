#!/bin/bash

# `git pull --rebase` and `git rebase` run this hook, not post-merge.
# ORIG_HEAD is the commit before the rebase. An amend changes nothing upstream.
[ "${1:-}" = "rebase" ] || exit 0
"$(git rev-parse --show-toplevel)/tools/git-hooks/sync-agent-config.sh" ORIG_HEAD HEAD

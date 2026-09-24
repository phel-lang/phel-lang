#!/bin/bash

# `git pull --rebase` and `git rebase` run this hook, not post-merge.
# ORIG_HEAD is the commit before the rebase. An amend changes nothing upstream.
[ "${1:-}" = "rebase" ] || exit 0
"$(dirname "$0")/sync-agent-config" ORIG_HEAD HEAD

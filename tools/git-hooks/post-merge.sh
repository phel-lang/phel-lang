#!/bin/bash

# `git pull` with merge or fast-forward. ORIG_HEAD is the commit before it.
"$(git rev-parse --show-toplevel)/tools/git-hooks/sync-agent-config.sh" ORIG_HEAD HEAD

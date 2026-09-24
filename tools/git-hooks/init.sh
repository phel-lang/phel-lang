#!/usr/bin/env bash

set -e

function setup_git_hooks()
{
  echo "Initialising git hooks..."
  local hooks
  hooks="$(git rev-parse --git-path hooks)"
  mkdir -p "$hooks"
  ln -sf "$PWD/tools/git-hooks/pre-commit.sh" "$hooks/pre-commit"
  # Copies, not symlinks: these run on checkout and pull, so the code they run
  # must not come from whatever branch was just checked out. Re-run init.sh to
  # pick up a change to them.
  local hook
  for hook in post-merge post-checkout post-rewrite; do
    rm -f "$hooks/$hook"
    cp "$PWD/tools/git-hooks/$hook.sh" "$hooks/$hook"
  done
  rm -f "$hooks/sync-agent-config"
  cp "$PWD/tools/git-hooks/sync-agent-config.sh" "$hooks/sync-agent-config"
  echo "Done"
}

setup_git_hooks

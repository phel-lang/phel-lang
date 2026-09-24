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
  local hook target
  for hook in post-merge post-checkout post-rewrite sync-agent-config; do
    target="$hooks/$hook"
    # Replace only what this script installed: a symlink into tools/git-hooks
    # (older installs) or a copy carrying the marker. Leave any other hook alone.
    if [ -e "$target" ] || [ -L "$target" ]; then
      if ! { [ -L "$target" ] && [[ "$(readlink "$target")" == */tools/git-hooks/* ]]; } \
        && ! grep -q '^# phel-lang git hook' "$target" 2>/dev/null; then
        echo "Skipping $hook: $target exists and was not installed by this script. Merge it by hand." >&2
        continue
      fi
      rm -f "$target"
    fi
    cp "$PWD/tools/git-hooks/$hook.sh" "$target"
  done
  echo "Done"
}

setup_git_hooks

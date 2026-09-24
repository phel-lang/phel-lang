#!/usr/bin/env bash

set -e

# Copies tools/git-hooks/<name>.sh to <hooks>/<name>. Replaces only what this
# script installed: a symlink into tools/git-hooks (older installs) or a copy
# carrying the marker. Returns 1 and leaves any other file alone.
function install_owned_copy()
{
  local hooks="$1" name="$2"
  local target="$hooks/$name"
  if [ -e "$target" ] || [ -L "$target" ]; then
    if ! { [ -L "$target" ] && [[ "$(readlink "$target")" == */tools/git-hooks/* ]]; } \
      && ! grep -q '^# phel-lang git hook' "$target" 2>/dev/null; then
      echo "Skipping $name: $target exists and was not installed by this script. Merge it by hand." >&2
      return 1
    fi
    rm -f "$target"
  fi
  cp "$PWD/tools/git-hooks/$name.sh" "$target"
}

function setup_git_hooks()
{
  echo "Initialising git hooks..."
  # Only ever this repository's own hooks directory. A core.hooksPath may be
  # shared by every repository on the machine, so installing there is refused.
  local hooks configured
  hooks="$(cd "$(git rev-parse --git-common-dir)" && pwd -P)/hooks"
  mkdir -p "$hooks"
  configured="$(git config --get core.hooksPath || true)"
  if [ -n "$configured" ] && [ "$(cd "$configured" 2>/dev/null && pwd -P)" != "$hooks" ]; then
    echo "core.hooksPath is set to $configured: not installing. Call tools/git-hooks/*.sh from your own hooks instead." >&2
    return 1
  fi
  local target="$hooks/pre-commit"
  if [ -e "$target" ] && ! { [ -L "$target" ] && [[ "$(readlink "$target")" == */tools/git-hooks/* ]]; }; then
    echo "Skipping pre-commit: $target exists and was not installed by this script. Merge it by hand." >&2
  else
    ln -sf "$PWD/tools/git-hooks/pre-commit.sh" "$target"
  fi
  # Copies, not symlinks: these run on checkout and pull, so the code they run
  # must not come from whatever branch was just checked out. Re-run init.sh to
  # pick up a change to them. The helper goes first: the hooks call it, so if it cannot be installed
  # (another tool owns that name) none of them are.
  local hook
  for hook in phel-sync-agent-config post-merge post-checkout post-rewrite; do
    if ! install_owned_copy "$hooks" "$hook" && [ "$hook" = phel-sync-agent-config ]; then
      break
    fi
  done
  echo "Done"
}

setup_git_hooks

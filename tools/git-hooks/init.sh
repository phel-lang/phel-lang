#!/usr/bin/env bash

set -e

function setup_git_hooks()
{
  echo "Initialising git hooks..."
  ln -sf "$PWD/tools/git-hooks/pre-commit.sh" "$PWD/.git/hooks/pre-commit"
  ln -sf "$PWD/tools/git-hooks/post-merge.sh" "$PWD/.git/hooks/post-merge"
  ln -sf "$PWD/tools/git-hooks/post-checkout.sh" "$PWD/.git/hooks/post-checkout"
  ln -sf "$PWD/tools/git-hooks/post-rewrite.sh" "$PWD/.git/hooks/post-rewrite"
  echo "Done"
}

setup_git_hooks

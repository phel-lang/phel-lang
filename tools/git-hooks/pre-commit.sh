#!/bin/bash

set -e

changed_files="$(git diff --cached --name-only)"

if ! printf '%s\n' "$changed_files" | grep -Eq '\.(php|phel)$'; then
  echo "Skipping composer test-all: no staged PHP or Phel files changed."
  exit 0
fi

composer test-all

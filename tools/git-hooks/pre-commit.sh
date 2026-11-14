#!/bin/bash

set -e

changed_files="$(git diff --cached --name-only)"

if ! printf '%s\n' "$changed_files" | grep -Eq '\.(php|phel)$'; then
  echo "Skipping composer test-commit: no staged PHP or Phel files changed."
  exit 0
fi

# Quality, unit and core only. The integration suite is about 85% of the test
# time and runs in CI on every pull request; run `composer test-compiler`
# before pushing to get it locally.
composer test-commit

#!/bin/bash
set -euo pipefail

root="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$root"

if [[ ! -x ./vendor/bin/php-cs-fixer ]]; then
  exit 0
fi

git diff --name-only --diff-filter=ACMRTUXB \
  | grep -E '\.php$' \
  | while IFS= read -r file; do
      [[ -f "$file" ]] || continue
      ./vendor/bin/php-cs-fixer fix --quiet "$file" >/dev/null 2>&1 || true
    done

exit 0

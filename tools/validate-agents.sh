#!/usr/bin/env bash
#
# Runs ./vendor/bin/phel test in each resources/agents/examples/* project against
# the local repo (symlinked via composer path repository). Exits non-zero
# on the first failing project. Invoke via `composer test-agents`.

set -euo pipefail

repo_root=$(cd "$(dirname "$0")/.." && pwd)
examples_dir="$repo_root/resources/agents/examples"

if [[ ! -d "$examples_dir" ]]; then
  echo "No examples directory at $examples_dir" >&2
  exit 1
fi

# Each example's `.phel/` (compiled cache + opcache) is throwaway state worth
# ~18M per project. Keep it outside the repo so the working tree stays small and
# `build/phar.sh` has nothing extra to sync.
phel_state_dir=$(mktemp -d "${TMPDIR:-/tmp}/phel-agents-XXXXXX")
trap 'rm -rf "$phel_state_dir"' EXIT

failures=0
for example in "$examples_dir"/*/; do
  name=$(basename "$example")
  [[ -f "$example/composer.json" ]] || continue

  echo "==> Validating example: $name"
  pushd "$example" > /dev/null

  # Point the example at the local phel-lang checkout via a path repository.
  if [[ ! -d vendor ]]; then
    cat > composer.local.json <<JSON
{
  "repositories": [
    { "type": "path", "url": "$repo_root", "options": { "symlink": true } }
  ],
  "require": { "phel-lang/phel-lang": "*@dev" },
  "minimum-stability": "dev",
  "prefer-stable": true
}
JSON
    COMPOSER=composer.local.json composer install --quiet --no-interaction
    rm -f composer.local.json composer.local.lock
  fi

  if PHEL_DIR="$phel_state_dir/$name" ./vendor/bin/phel test; then
    echo "    OK: $name"
  else
    echo "    FAIL: $name"
    failures=$((failures + 1))
  fi

  popd > /dev/null
done

if (( failures > 0 )); then
  echo "$failures example(s) failed"
  exit 1
fi

echo "All examples validated"

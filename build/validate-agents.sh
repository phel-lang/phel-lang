#!/usr/bin/env bash
#
# Phase 6: validate .agents/ drift against the live language.
#
# For every project under .agents/examples/, this script:
#   1. symlinks the repo as a local Composer path dep
#   2. runs ./vendor/bin/phel test
#
# Exits 0 when every example compiles and every test passes.
# Exits 1 on the first failure, printing the offending example.
#
# Run manually:   ./build/validate-agents.sh
# Or via composer: composer test-agents

set -euo pipefail

repo_root=$(cd "$(dirname "$0")/.." && pwd)
examples_dir="$repo_root/.agents/examples"

if [[ ! -d "$examples_dir" ]]; then
  echo "No examples directory at $examples_dir" >&2
  exit 1
fi

failures=0
for example in "$examples_dir"/*/; do
  name=$(basename "$example")
  [[ -f "$example/phel-config.php" ]] || continue

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

  if ./vendor/bin/phel test; then
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

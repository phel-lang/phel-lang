#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
WORK_DIR="$SCRIPT_DIR/workdir"

rm -rf "$WORK_DIR" "$SCRIPT_DIR/out/phel.phar"
mkdir -p "$WORK_DIR"

rsync -a "$REPO_ROOT/" "$WORK_DIR/" \
  --exclude='.git' \
  --exclude='docs' \
  --exclude='tests' \
  --exclude='docker' \
  --exclude='data' \
  --exclude='vendor' \
  --exclude='build'

pushd "$WORK_DIR" >/dev/null
composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts
php -d phar.readonly=0 "$SCRIPT_DIR/build-phar.php" "$WORK_DIR"
popd >/dev/null

mv "$WORK_DIR/phel.phar" "$SCRIPT_DIR/out/phel.phar"
rm -rf "$WORK_DIR"

echo "PHAR created at $SCRIPT_DIR/out/phel.phar"

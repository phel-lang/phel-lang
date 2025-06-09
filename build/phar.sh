#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
WORK_DIR="$SCRIPT_DIR/workdir"
CACHE_DIR="$SCRIPT_DIR/.phar-cache"
COMPOSER_CACHE_DIR="$CACHE_DIR/composer"
VENDOR_CACHE_DIR="$CACHE_DIR/vendor"

rm -rf "$WORK_DIR" "$SCRIPT_DIR/out/phel.phar"
mkdir -p "$WORK_DIR" "$CACHE_DIR"

# Sync project files, excluding large and unnecessary dirs
rsync -a "$REPO_ROOT/" "$WORK_DIR/" \
  --exclude='.git' \
  --exclude='docs' \
  --exclude='tests' \
  --exclude='docker' \
  --exclude='data' \
  --exclude='vendor' \
  --exclude='build'

# Use cached composer cache
export COMPOSER_CACHE_DIR

pushd "$WORK_DIR" >/dev/null

# Optionally reuse vendor dir if it exists and is still valid
if [[ -d "$VENDOR_CACHE_DIR" ]]; then
  echo "Using cached vendor directory"
  cp -a "$VENDOR_CACHE_DIR" ./vendor
fi

composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts

# Update vendor cache if it changed
rm -rf "$VENDOR_CACHE_DIR"
cp -a ./vendor "$VENDOR_CACHE_DIR"

# Build the PHAR
php -d phar.readonly=0 "$SCRIPT_DIR/build-phar.php" "$WORK_DIR"
popd >/dev/null

mv "$WORK_DIR/phel.phar" "$SCRIPT_DIR/out/phel.phar"
rm -rf "$WORK_DIR"

chmod +x "$SCRIPT_DIR/out/phel.phar"

echo "PHAR created at $SCRIPT_DIR/out/phel.phar"

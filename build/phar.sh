#!/usr/bin/env bash
set -euo pipefail

# Optional: Pass OFFICIAL_RELEASE=true to build an official release PHAR
# Usage: OFFICIAL_RELEASE=true build/phar.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
WORK_DIR="$SCRIPT_DIR/workdir"
CACHE_DIR="$SCRIPT_DIR/.phar-cache"
VENDOR_CACHE_DIR="$CACHE_DIR/vendor"
LOCK_FILE_CACHE="$CACHE_DIR/composer.lock"

# Cleanup previous builds
echo "🧹 Cleaning up..."
rm -rf "$WORK_DIR" "$SCRIPT_DIR/out/phel.phar"
mkdir -p "$WORK_DIR" "$CACHE_DIR"

echo "📋 Syncing project files..."
# Sync project files, excluding large and unnecessary dirs
rsync -a "$REPO_ROOT/" "$WORK_DIR/" \
  --exclude='.git' \
  --exclude='docs' \
  --exclude='tests' \
  --exclude='docker' \
  --exclude='data' \
  --exclude='vendor' \
  --exclude='build' \
  --exclude='/.phel-cache' \
  --delete

pushd "$WORK_DIR" >/dev/null

# Check if composer.lock changed to invalidate vendor cache
VENDOR_CACHE_VALID=0
if [[ -d "$VENDOR_CACHE_DIR" ]] && [[ -f "$LOCK_FILE_CACHE" ]]; then
  if cmp -s composer.lock "$LOCK_FILE_CACHE" 2>/dev/null; then
    echo "✅ Using cached vendor directory"
    cp -a "$VENDOR_CACHE_DIR" ./vendor
    VENDOR_CACHE_VALID=1
  fi
fi

if [[ $VENDOR_CACHE_VALID -eq 0 ]]; then
  echo "📦 Installing dependencies..."
  composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --classmap-authoritative \
    --no-scripts

  # Update vendor cache
  rm -rf "$VENDOR_CACHE_DIR"
  cp -a ./vendor "$VENDOR_CACHE_DIR"
  cp composer.lock "$LOCK_FILE_CACHE"
fi

# Build the PHAR
echo "🔨 Building PHAR..."
OFFICIAL_RELEASE="${OFFICIAL_RELEASE:-}" php -d phar.readonly=0 "$SCRIPT_DIR/build-phar.php" "$WORK_DIR"
popd >/dev/null

mv "$WORK_DIR/phel.phar" "$SCRIPT_DIR/out/phel.phar"
rm -rf "$WORK_DIR"

chmod +x "$SCRIPT_DIR/out/phel.phar"

echo "✨ PHAR created at $SCRIPT_DIR/out/phel.phar"

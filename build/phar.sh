#!/usr/bin/env bash
set -euo pipefail

# Build Phel PHAR archive
# Optional environment variables:
#   OFFICIAL_RELEASE=true|1|yes  - Build an official release (no beta flag)
#   DEBUG=1                       - Enable debug output
#   SKIP_CACHE=1                  - Skip vendor cache and rebuild fresh
# Usage: OFFICIAL_RELEASE=true build/phar.sh

# ============================================================================
# Configuration
# ============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
WORK_DIR="$SCRIPT_DIR/workdir"
CACHE_DIR="$SCRIPT_DIR/.phar-cache"
VENDOR_CACHE_DIR="$CACHE_DIR/vendor"
LOCK_FILE_CACHE="$CACHE_DIR/composer.lock"
OUTPUT_DIR="$SCRIPT_DIR/out"
PHAR_FILE="$OUTPUT_DIR/phel.phar"
BUILD_SCRIPT="$SCRIPT_DIR/build-phar.php"

# Environment variable defaults
SKIP_CACHE="${SKIP_CACHE:-0}"
OFFICIAL_RELEASE="${OFFICIAL_RELEASE:-}"

# ============================================================================
# Error Handling
# ============================================================================
error() {
    echo "Error: $@" >&2
    exit 1
}

# ============================================================================
# Validation Functions
# ============================================================================
check_command() {
    local cmd="$1"
    if ! command -v "$cmd" &>/dev/null; then
        error "Required command '$cmd' not found. Please install it and try again."
    fi
}

check_file() {
    local file="$1"
    if [[ ! -f "$file" ]]; then
        error "Required file not found: $file"
    fi
}

check_dir() {
    local dir="$1"
    if [[ ! -d "$dir" ]]; then
        error "Required directory not found: $dir"
    fi
}

# ============================================================================
# Cleanup & Error Handling
# ============================================================================
cleanup() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]] && [[ -d "$WORK_DIR" ]]; then
        rm -rf "$WORK_DIR"
    fi
}

trap cleanup EXIT

# ============================================================================
# Prerequisites Check
# ============================================================================
check_command "php"
check_command "composer"
check_command "rsync"
check_file "$BUILD_SCRIPT"
check_dir "$REPO_ROOT"

# Validate PHP version (minimum 7.4)
php_version=$(php -r 'echo version_compare(PHP_VERSION, "7.4", ">=") ? "OK" : "FAIL";')
if [[ "$php_version" != "OK" ]]; then
    error "PHP 7.4 or higher is required"
fi

# ============================================================================
# Cleanup & Setup
# ============================================================================
[[ -d "$WORK_DIR" ]] && rm -rf "$WORK_DIR"
[[ -f "$PHAR_FILE" ]] && rm -f "$PHAR_FILE"

mkdir -p "$WORK_DIR" "$CACHE_DIR" "$OUTPUT_DIR"

# ============================================================================
# Sync Project Files
# ============================================================================
rsync -a "$REPO_ROOT/" "$WORK_DIR/" \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='docs' \
  --exclude='tests' \
  --exclude='docker' \
  --exclude='data' \
  --exclude='vendor' \
  --exclude='build' \
  --exclude='/.phel-cache' \
  --exclude='.idea' \
  --exclude='.vscode' \
  --exclude='node_modules' \
  --delete

# ============================================================================
# Dependency Management with Caching
# ============================================================================
pushd "$WORK_DIR" >/dev/null

VENDOR_CACHE_VALID=0
if [[ $SKIP_CACHE -eq 0 ]] && [[ -d "$VENDOR_CACHE_DIR" ]] && [[ -f "$LOCK_FILE_CACHE" ]]; then
    if cmp -s composer.lock "$LOCK_FILE_CACHE" 2>/dev/null; then
        cp -a "$VENDOR_CACHE_DIR" ./vendor
        VENDOR_CACHE_VALID=1
    fi
fi

if [[ $VENDOR_CACHE_VALID -eq 0 ]]; then
    if ! composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --classmap-authoritative \
        --no-scripts; then
        error "Composer install failed"
    fi

    rm -rf "$VENDOR_CACHE_DIR"
    cp -a ./vendor "$VENDOR_CACHE_DIR"
    cp composer.lock "$LOCK_FILE_CACHE"
fi

# Validate vendor directory
if [[ ! -d "./vendor" ]]; then
    error "Vendor directory not found after dependency installation"
fi

# ============================================================================
# Build PHAR Archive
# ============================================================================
export OFFICIAL_RELEASE SCRIPT_DIR

if ! php -d phar.readonly=0 "$BUILD_SCRIPT" "$WORK_DIR"; then
    error "PHAR build failed"
fi

if [[ ! -f "$WORK_DIR/phel.phar" ]]; then
    error "PHAR file was not created by build script"
fi

popd >/dev/null

# Move PHAR to output directory
mv "$WORK_DIR/phel.phar" "$PHAR_FILE"
rm -rf "$WORK_DIR"

# Validate created PHAR
if [[ ! -x "$PHAR_FILE" ]]; then
    error "PHAR file is not executable"
fi

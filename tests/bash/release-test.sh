#!/usr/bin/env bash

# Bashunit tests for release-lib.sh
# Run with: tools/bashunit tests/bash/release-test.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"

# Source the release library
source "$REPO_ROOT/build/release-lib.sh"

# Mock external commands to prevent real operations
function git() { echo "mocked"; }
function gh() { echo "mocked"; }
function curl() { return 0; }
export -f git gh curl

# Test fixtures
TEMP_DIR=""

function set_up() {
    TEMP_DIR=$(mktemp -d)
}

function tear_down() {
    [[ -n "$TEMP_DIR" ]] && rm -rf "$TEMP_DIR"
}

# Helper to check if a command fails (returns non-zero)
assert_fails() {
    local result=0
    eval "$1" 2>/dev/null || result=$?
    if [[ $result -eq 0 ]]; then
        fail "Expected command to fail: $1"
    fi
}

# =============================================================================
# Version Validation Tests
# =============================================================================

function test_validate_semver_valid() {
    assert_successful_code "validate_semver '1.2.3'"
}

function test_validate_semver_valid_with_zeros() {
    assert_successful_code "validate_semver '0.0.0'"
}

function test_validate_semver_valid_large_numbers() {
    assert_successful_code "validate_semver '100.200.300'"
}

function test_validate_semver_invalid_letters() {
    local result=0
    validate_semver 'abc' || result=$?
    assert_equals "1" "$result"
}

function test_validate_semver_invalid_partial() {
    local result=0
    validate_semver '1.2' || result=$?
    assert_equals "1" "$result"
}

function test_validate_semver_invalid_extra_parts() {
    local result=0
    validate_semver '1.2.3.4' || result=$?
    assert_equals "1" "$result"
}

function test_validate_semver_invalid_with_v_prefix() {
    local result=0
    validate_semver 'v1.2.3' || result=$?
    assert_equals "1" "$result"
}

function test_validate_semver_invalid_empty() {
    local result=0
    validate_semver '' || result=$?
    assert_equals "1" "$result"
}

# =============================================================================
# Version Comparison Tests
# =============================================================================

function test_version_gt_greater() {
    assert_successful_code "version_gt '1.1.0' '1.0.0'"
}

function test_version_gt_equal() {
    local result=0
    version_gt '1.0.0' '1.0.0' || result=$?
    assert_equals "1" "$result"
}

function test_version_gt_less() {
    local result=0
    version_gt '0.9.0' '1.0.0' || result=$?
    assert_equals "1" "$result"
}

function test_version_gt_major_wins() {
    assert_successful_code "version_gt '2.0.0' '1.9.9'"
}

function test_version_gt_minor_wins() {
    assert_successful_code "version_gt '1.2.0' '1.1.9'"
}

function test_version_gt_patch_wins() {
    assert_successful_code "version_gt '1.0.1' '1.0.0'"
}

function test_version_gt_major_less() {
    local result=0
    version_gt '1.9.9' '2.0.0' || result=$?
    assert_equals "1" "$result"
}

function test_version_gt_minor_less() {
    local result=0
    version_gt '1.1.9' '1.2.0' || result=$?
    assert_equals "1" "$result"
}

# =============================================================================
# Version Extraction Tests
# =============================================================================

function test_get_current_version_extracts_version() {
    local version_file="$TEMP_DIR/VersionFinder.php"
    cat > "$version_file" << 'EOF'
<?php
final class VersionFinder {
    public const string LATEST_VERSION = 'v0.27.0';
}
EOF

    local result
    result=$(get_current_version "$version_file")
    assert_equals "0.27.0" "$result"
}

function test_get_current_version_different_version() {
    local version_file="$TEMP_DIR/VersionFinder.php"
    cat > "$version_file" << 'EOF'
<?php
final class VersionFinder {
    public const string LATEST_VERSION = 'v1.2.3';
}
EOF

    local result
    result=$(get_current_version "$version_file")
    assert_equals "1.2.3" "$result"
}

function test_get_current_version_missing_file() {
    local result=0
    get_current_version "$TEMP_DIR/nonexistent.php" 2>/dev/null || result=$?
    assert_equals "1" "$result"
}

function test_get_current_version_invalid_format() {
    local version_file="$TEMP_DIR/VersionFinder.php"
    cat > "$version_file" << 'EOF'
<?php
final class VersionFinder {
    public const string LATEST_VERSION = 'invalid';
}
EOF

    local result=0
    get_current_version "$version_file" 2>/dev/null || result=$?
    assert_equals "1" "$result"
}

# =============================================================================
# Argument Parsing Tests
# =============================================================================

function test_parse_args_version_only() {
    parse_args "1.2.3"
    assert_equals "1.2.3" "$NEW_VERSION"
    assert_equals "0" "$DRY_RUN"
    assert_equals "0" "$FORCE"
    assert_equals "0" "$SKIP_PHAR"
}

function test_parse_args_dry_run() {
    parse_args --dry-run "1.2.3"
    assert_equals "1" "$DRY_RUN"
    assert_equals "1.2.3" "$NEW_VERSION"
}

function test_parse_args_force() {
    parse_args --force "1.2.3"
    assert_equals "1" "$FORCE"
    assert_equals "1.2.3" "$NEW_VERSION"
}

function test_parse_args_skip_phar() {
    parse_args --skip-phar "1.2.3"
    assert_equals "1" "$SKIP_PHAR"
    assert_equals "1.2.3" "$NEW_VERSION"
}

function test_parse_args_all_flags() {
    parse_args --dry-run --force --skip-phar "1.2.3"
    assert_equals "1" "$DRY_RUN"
    assert_equals "1" "$FORCE"
    assert_equals "1" "$SKIP_PHAR"
    assert_equals "1.2.3" "$NEW_VERSION"
}

function test_parse_args_missing_version() {
    local result=0
    parse_args 2>/dev/null || result=$?
    assert_equals "1" "$result"
}

function test_parse_args_unknown_option() {
    local result=0
    parse_args --unknown 1.2.3 2>/dev/null || result=$?
    assert_equals "1" "$result"
}

function test_parse_args_help_returns_2() {
    local result=0
    parse_args --help || result=$?
    assert_equals "2" "$result"
}

# =============================================================================
# File Update Tests
# =============================================================================

function test_update_version_finder() {
    local version_file="$TEMP_DIR/VersionFinder.php"
    cat > "$version_file" << 'EOF'
<?php
final class VersionFinder {
    public const string LATEST_VERSION = 'v0.27.0';
}
EOF

    update_version_finder "0.28.0" "$version_file"

    local result
    result=$(cat "$version_file")
    assert_contains "v0.28.0" "$result"
    assert_not_contains "v0.27.0" "$result"
}

function test_update_changelog() {
    local changelog_file="$TEMP_DIR/CHANGELOG.md"
    cat > "$changelog_file" << 'EOF'
# Changelog

## Unreleased

### Added
- New feature

## [0.27.0](https://github.com/phel-lang/phel-lang/compare/v0.26.0...v0.27.0) - 2024-01-01

### Fixed
- Old fix
EOF

    update_changelog "0.28.0" "$changelog_file" "0.27.0"

    local result
    result=$(cat "$changelog_file")
    assert_contains "## Unreleased" "$result"
    assert_contains "## [0.28.0]" "$result"
    assert_contains "v0.27.0...v0.28.0" "$result"
}

function test_extract_release_notes() {
    local changelog_file="$TEMP_DIR/CHANGELOG.md"
    cat > "$changelog_file" << 'EOF'
# Changelog

## Unreleased

## [0.28.0](https://github.com/phel-lang/phel-lang/compare/v0.27.0...v0.28.0) - 2024-02-01

### Added
- New feature

### Fixed
- Bug fix

## [0.27.0](https://github.com/phel-lang/phel-lang/compare/v0.26.0...v0.27.0) - 2024-01-01

### Fixed
- Old fix
EOF

    local result
    result=$(extract_release_notes "0.28.0" "$changelog_file")
    assert_contains "New feature" "$result"
    assert_contains "Bug fix" "$result"
    assert_not_contains "Old fix" "$result"
}

function test_extract_release_notes_empty() {
    local changelog_file="$TEMP_DIR/CHANGELOG.md"
    cat > "$changelog_file" << 'EOF'
# Changelog

## [0.28.0](link) - 2024-02-01

## [0.27.0](link) - 2024-01-01

### Fixed
- Old fix
EOF

    local result
    result=$(extract_release_notes "0.28.0" "$changelog_file")
    assert_empty "$result"
}

# =============================================================================
# Backup & Restore Tests
# =============================================================================

function test_create_backup() {
    local backup_dir="$TEMP_DIR/backup"
    mkdir -p "$backup_dir"

    local version_file="$TEMP_DIR/VersionFinder.php"
    local changelog_file="$TEMP_DIR/CHANGELOG.md"
    echo "version content" > "$version_file"
    echo "changelog content" > "$changelog_file"

    create_backup "$backup_dir" "$version_file" "$changelog_file"

    assert_file_exists "$backup_dir/VersionFinder.php"
    assert_file_exists "$backup_dir/CHANGELOG.md"
    assert_equals "version content" "$(cat "$backup_dir/VersionFinder.php")"
    assert_equals "changelog content" "$(cat "$backup_dir/CHANGELOG.md")"
}

function test_restore_backup() {
    local backup_dir="$TEMP_DIR/backup"
    mkdir -p "$backup_dir"

    local version_file="$TEMP_DIR/VersionFinder.php"
    local changelog_file="$TEMP_DIR/CHANGELOG.md"

    # Create original files
    echo "original version" > "$version_file"
    echo "original changelog" > "$changelog_file"

    # Create backup
    create_backup "$backup_dir" "$version_file" "$changelog_file"

    # Modify original files
    echo "modified version" > "$version_file"
    echo "modified changelog" > "$changelog_file"

    # Restore
    restore_backup "$backup_dir" "$version_file" "$changelog_file"

    assert_equals "original version" "$(cat "$version_file")"
    assert_equals "original changelog" "$(cat "$changelog_file")"
}

# =============================================================================
# Changelog Unreleased Check Tests
# =============================================================================

function test_check_changelog_unreleased_has_content() {
    local changelog_file="$TEMP_DIR/CHANGELOG.md"
    cat > "$changelog_file" << 'EOF'
# Changelog

## Unreleased

### Added
- New feature

## [0.27.0](link) - 2024-01-01
EOF

    assert_successful_code "check_changelog_unreleased '$changelog_file' 2>/dev/null"
}

function test_check_changelog_unreleased_empty() {
    local changelog_file="$TEMP_DIR/CHANGELOG.md"
    cat > "$changelog_file" << 'EOF'
# Changelog

## Unreleased

## [0.27.0](link) - 2024-01-01
EOF

    local result=0
    check_changelog_unreleased "$changelog_file" 2>/dev/null || result=$?
    assert_equals "1" "$result"
}

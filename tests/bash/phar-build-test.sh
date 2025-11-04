#!/usr/bin/env bash

# Bashunit end-to-end integration tests for PHAR build script
# Run with: tools/bashunit tests/bash/phar-build.sh

set -euo pipefail

function cleanup_phar() {
    rm -f build/out/phel.phar 2>/dev/null || true
    rm -f .phel-release.php 2>/dev/null || true
}

function set_up() {
    cleanup_phar
}

function tear_down_after_script() {
    cleanup_phar
}

# Comprehensive end-to-end test for PHAR build system
# Validates all PHAR building functionality in a single test
function test_phar_beta_build() {
    # Build PHAR (redirect output to avoid noise in test output)
    bash build/create-phar.sh > /dev/null 2>&1

    # Verify PHAR file was created
    assert_file_exists "build/out/phel.phar"

    # Verify PHAR is executable and shows version with beta flag
    local version
    version=$(build/out/phel.phar --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.24.0-beta#hash") regardless of specific version
    assert_contains "Phel v" "$version"
    assert_contains "-beta#" "$version"

    # Verify PHAR can execute Phel code
    local result
    result=$(build/out/phel.phar eval '(println "test")' 2>&1)
    assert_contains "test" "$result"
}

function test_phar_official_release_build() {
    # Build official release PHAR
    OFFICIAL_RELEASE=true bash build/create-phar.sh > /dev/null 2>&1

    # Verify PHAR file was created
    assert_file_exists "build/out/phel.phar"

    # Verify official release does NOT show beta flag
    version=$(build/out/phel.phar --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.24.0#hash") but without beta flag
    assert_contains "Phel v" "$version"
    assert_not_contains "beta" "$version"

    # Verify official release PHAR can still execute code
    result=$(build/out/phel.phar eval '(println "official")' 2>&1)
    assert_contains "official" "$result"
}

function test_phar_official_false_creates_beta_build() {
    OFFICIAL_RELEASE=false bash build/create-phar.sh > /dev/null 2>&1
    assert_file_exists "build/out/phel.phar"

    # Verify beta flag is present
    version=$(build/out/phel.phar --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.24.0-beta#hash") regardless of specific version
    assert_contains "Phel v" "$version"
    assert_contains "-beta#" "$version"
}

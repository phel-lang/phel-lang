#!/usr/bin/env bash

# Bashunit end-to-end integration tests for PHAR build script
# Run with: tools/bashunit tests/bash/phar-build.sh

set -euo pipefail

# Helper function to clean up before tests
function cleanup_phar() {
    rm -f build/out/phel.phar 2>/dev/null || true
    rm -f .phel-release.php 2>/dev/null || true
}

# Comprehensive end-to-end test for PHAR build system
# Validates all PHAR building functionality in a single test
function test_phar_build_e2e() {
    echo "Starting comprehensive PHAR build e2e test..."

    # ========== Test 1: Normal build (beta) ==========
    echo ""
    echo "Testing normal (beta) PHAR build..."
    cleanup_phar

    # Build PHAR
    local build_output
    build_output=$(bash build/phar.sh 2>&1)

    # Verify build output shows expected messages
    assert_contains "Cleaning up" "$build_output"
    assert_contains "Syncing project files" "$build_output"
    assert_contains "Building PHAR" "$build_output"
    assert_contains "PHAR created at" "$build_output"

    # Verify PHAR file was created
    assert_file_exists "build/out/phel.phar"

    # Verify PHAR is executable and shows version with beta flag
    local version
    version=$(build/out/phel.phar --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.24.0-beta#hash") regardless of specific version
    assert_contains "Phel v" "$version"
    assert_contains "beta" "$version"

    # Verify PHAR can execute Phel code
    local result
    result=$(build/out/phel.phar eval '(println "test")' 2>&1)
    assert_contains "test" "$result"

    echo "✅ Normal build tests passed"

    # ========== Test 2: Official release build ==========
    echo ""
    echo "Testing official release PHAR build..."
    cleanup_phar

    # Build official release PHAR
    OFFICIAL_RELEASE=true bash build/phar.sh > /dev/null 2>&1

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

    echo "✅ Official release tests passed"

    # ========== Test 3: Explicit false flag creates beta build ==========
    echo ""
    echo "Testing OFFICIAL_RELEASE=false creates beta build..."
    cleanup_phar

    # Build with explicit false flag
    OFFICIAL_RELEASE=false bash build/phar.sh > /dev/null 2>&1

    # Verify PHAR file was created
    assert_file_exists "build/out/phel.phar"

    # Verify beta flag is present
    version=$(build/out/phel.phar --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.24.0-beta#hash") regardless of specific version
    assert_contains "Phel v" "$version"
    assert_contains "beta" "$version"

    echo "✅ OFFICIAL_RELEASE=false tests passed"

    # Final cleanup
    cleanup_phar

    echo ""
    echo "✅ All PHAR build e2e tests passed successfully!"
}

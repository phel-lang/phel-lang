#!/usr/bin/env bash

set -euo pipefail

function set_up() {
    PHAR_TEST_OUTPUT_DIR="${TMPDIR:-/tmp/}phel-phar-test-$$-$RANDOM"
    mkdir -p "$PHAR_TEST_OUTPUT_DIR"
}

function tear_down() {
    rm -rf "$PHAR_TEST_OUTPUT_DIR" 2>/dev/null || true
}

function test_phar_beta_build() {
    # Build PHAR with custom output directory (redirect output to avoid noise in test output)
    bash build/create-phar.sh "$PHAR_TEST_OUTPUT_DIR" > /dev/null 2>&1

    # Verify PHAR file was created
    assert_file_exists "$PHAR_TEST_OUTPUT_DIR/phel.phar"

    # Verify PHAR is executable and shows version with beta flag
    local version
    version=$("$PHAR_TEST_OUTPUT_DIR/phel.phar" --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.24.0-beta#hash") regardless of specific version
    assert_contains "Phel v" "$version"
    assert_contains "-beta#" "$version"

    # Verify PHAR can execute Phel code
    local result
    result=$("$PHAR_TEST_OUTPUT_DIR/phel.phar" eval '(println "test")' 2>&1)
    assert_contains "test" "$result"
}

function test_phar_official_release_build() {
    # Build official release PHAR with custom output directory
    OFFICIAL_RELEASE=true bash build/create-phar.sh "$PHAR_TEST_OUTPUT_DIR" > /dev/null 2>&1

    # Verify PHAR file was created
    assert_file_exists "$PHAR_TEST_OUTPUT_DIR/phel.phar"

    # Verify official release does NOT show beta flag
    version=$("$PHAR_TEST_OUTPUT_DIR/phel.phar" --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.25.0#hash") but without beta flag
    assert_contains "Phel v" "$version"
    assert_not_contains "beta" "$version"

    # Verify official release PHAR can still execute code
    result=$("$PHAR_TEST_OUTPUT_DIR/phel.phar" eval '(println "official")' 2>&1)
    assert_contains "official" "$result"
}

function test_phar_official_false_creates_beta_build() {
    OFFICIAL_RELEASE=false bash build/create-phar.sh "$PHAR_TEST_OUTPUT_DIR" > /dev/null 2>&1
    assert_file_exists "$PHAR_TEST_OUTPUT_DIR/phel.phar"

    # Verify beta flag is present
    version=$("$PHAR_TEST_OUTPUT_DIR/phel.phar" --version 2>&1)
    # Check for version pattern (e.g., "Phel v0.25.0-beta#hash") regardless of specific version
    assert_contains "Phel v" "$version"
    assert_contains "-beta#" "$version"
}

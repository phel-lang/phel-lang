#!/usr/bin/env bash

set -euo pipefail

AFFECTED_SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/benchmark-affected.sh"
AFFECTED_FIXTURE=""

function set_up() {
    AFFECTED_FIXTURE=$(mktemp -d)
    git -C "$AFFECTED_FIXTURE" init --quiet
    git -C "$AFFECTED_FIXTURE" config user.name 'Benchmark test'
    git -C "$AFFECTED_FIXTURE" config user.email 'benchmark@example.test'
    git -C "$AFFECTED_FIXTURE" commit --quiet --allow-empty -m base
}

function tear_down() {
    if [[ -n "$AFFECTED_FIXTURE" && "$AFFECTED_FIXTURE" == "${TMPDIR:-/tmp/}"* ]]; then
        rm -rf "$AFFECTED_FIXTURE"
    fi
}

function commit_files() {
    local file
    for file in "$@"; do
        mkdir -p "$AFFECTED_FIXTURE/$(dirname "$file")"
        echo change >> "$AFFECTED_FIXTURE/$file"
    done
    git -C "$AFFECTED_FIXTURE" add -A
    git -C "$AFFECTED_FIXTURE" commit --quiet -m change
}

function affected() {
    (cd "$AFFECTED_FIXTURE" && bash "$AFFECTED_SCRIPT" HEAD~1 HEAD)
}

function test_docs_only_change_is_not_affected() {
    commit_files docs/guide.md CHANGELOG.md .agnostic-ai/rules/x.md .github/workflows/release.yml
    assert_equals 'affected=false' "$(affected)"
}

function test_a_source_change_is_affected() {
    commit_files docs/guide.md src/php/Lang/Foo.php
    assert_equals 'affected=true' "$(affected)"
}

function test_a_phel_source_change_is_affected() {
    commit_files src/phel/core.phel
    assert_equals 'affected=true' "$(affected)"
}

function test_benchmark_inputs_outside_src_are_affected() {
    local file
    for file in tests/php/Benchmark/FooBench.php phpbench.json composer.json composer.lock \
        .github/workflows/tests.yml .github/actions/setup-phel/action.yml tools/benchmark-revisions.sh; do
        commit_files "$file"
        assert_equals 'affected=true' "$(affected)" "$file"
    done
}

function test_a_lookalike_path_is_not_affected() {
    commit_files docs/src/page.md tests/php/Unit/BenchmarkTest.php phpbench.json.dist
    assert_equals 'affected=false' "$(affected)"
}

function test_it_requires_two_revisions() {
    local output status=0
    output=$(bash "$AFFECTED_SCRIPT" HEAD 2>&1) || status=$?
    assert_equals 1 "$status"
    assert_contains 'Usage:' "$output"
}

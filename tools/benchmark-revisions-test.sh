#!/usr/bin/env bash

set -euo pipefail

BENCHMARK_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BENCHMARK_REVISIONS="$BENCHMARK_SCRIPT_DIR/benchmark-revisions.sh"
BENCHMARK_FIXTURE=""

function set_up() {
    BENCHMARK_FIXTURE=$(mktemp -d)
    git -C "$BENCHMARK_FIXTURE" init --quiet
    git -C "$BENCHMARK_FIXTURE" config user.name 'Benchmark test'
    git -C "$BENCHMARK_FIXTURE" config user.email 'benchmark@example.test'
    git -C "$BENCHMARK_FIXTURE" commit --quiet --allow-empty -m base
    BENCHMARK_STALE_BASE=$(git -C "$BENCHMARK_FIXTURE" rev-parse HEAD)
    git -C "$BENCHMARK_FIXTURE" checkout --quiet -b contribution
    git -C "$BENCHMARK_FIXTURE" commit --quiet --allow-empty -m contribution
    BENCHMARK_PR_HEAD=$(git -C "$BENCHMARK_FIXTURE" rev-parse HEAD)
    git -C "$BENCHMARK_FIXTURE" checkout --quiet -b advanced-main "$BENCHMARK_STALE_BASE"
    git -C "$BENCHMARK_FIXTURE" commit --quiet --allow-empty -m advanced
    BENCHMARK_ACTUAL_BASE=$(git -C "$BENCHMARK_FIXTURE" rev-parse HEAD)
    git -C "$BENCHMARK_FIXTURE" merge --quiet --no-ff contribution -m tested-merge
    BENCHMARK_MERGE=$(git -C "$BENCHMARK_FIXTURE" rev-parse HEAD)
}

function tear_down() {
    if [[ -n "$BENCHMARK_FIXTURE" && "$BENCHMARK_FIXTURE" == "${TMPDIR:-/tmp/}"* ]]; then
        printf 'Removing benchmark fixture: %s\n' "$BENCHMARK_FIXTURE"
        rm -rf "$BENCHMARK_FIXTURE"
    fi
}

function test_pr_uses_actual_merge_parent_and_keeps_frozen_revisions_after_checkout() {
    local captured
    captured=$(cd "$BENCHMARK_FIXTURE" && bash "$BENCHMARK_REVISIONS" pull_request "$BENCHMARK_MERGE" "$BENCHMARK_PR_HEAD")
    assert_contains "HEAD_SHA=$BENCHMARK_MERGE" "$captured"
    assert_contains "BASE_SHA=$BENCHMARK_ACTUAL_BASE" "$captured"
    assert_not_contains "BASE_SHA=$BENCHMARK_STALE_BASE" "$captured"

    git -C "$BENCHMARK_FIXTURE" checkout --quiet --detach "$BENCHMARK_STALE_BASE"
    assert_contains "HEAD_SHA=$BENCHMARK_MERGE" "$captured"
    assert_contains "BASE_SHA=$BENCHMARK_ACTUAL_BASE" "$captured"
}

function test_pr_rejects_a_non_merge_checkout() {
    git -C "$BENCHMARK_FIXTURE" checkout --quiet --detach "$BENCHMARK_PR_HEAD"
    local output status=0
    output=$(cd "$BENCHMARK_FIXTURE" && bash "$BENCHMARK_REVISIONS" pull_request "$BENCHMARK_PR_HEAD" "$BENCHMARK_PR_HEAD" 2>&1) || status=$?
    assert_equals 1 "$status"
    assert_contains 'Expected the checked-out PR merge' "$output"
}

function test_pr_rejects_the_wrong_second_parent() {
    local output status=0
    output=$(cd "$BENCHMARK_FIXTURE" && bash "$BENCHMARK_REVISIONS" pull_request "$BENCHMARK_MERGE" "$BENCHMARK_STALE_BASE" 2>&1) || status=$?
    assert_equals 1 "$status"
    assert_contains 'Expected the checked-out PR merge' "$output"
}

function test_pr_rejects_a_merge_with_more_than_two_parents() {
    git -C "$BENCHMARK_FIXTURE" checkout --quiet -b other "$BENCHMARK_STALE_BASE"
    git -C "$BENCHMARK_FIXTURE" commit --quiet --allow-empty -m other
    git -C "$BENCHMARK_FIXTURE" checkout --quiet --detach "$BENCHMARK_ACTUAL_BASE"
    git -C "$BENCHMARK_FIXTURE" merge --quiet --no-ff contribution other -m octopus
    local merge output status=0
    merge=$(git -C "$BENCHMARK_FIXTURE" rev-parse HEAD)
    output=$(cd "$BENCHMARK_FIXTURE" && bash "$BENCHMARK_REVISIONS" pull_request "$merge" "$BENCHMARK_PR_HEAD" 2>&1) || status=$?
    assert_equals 1 "$status"
    assert_contains 'Expected the checked-out PR merge' "$output"
}

function test_pr_rejects_a_checkout_different_from_the_tested_event_merge() {
    local output status=0
    output=$(cd "$BENCHMARK_FIXTURE" && bash "$BENCHMARK_REVISIONS" pull_request "$BENCHMARK_ACTUAL_BASE" "$BENCHMARK_PR_HEAD" 2>&1) || status=$?
    assert_equals 1 "$status"
    assert_contains 'Expected the checked-out PR merge' "$output"
}

function test_non_pr_keeps_the_event_predecessor_behavior() {
    git -C "$BENCHMARK_FIXTURE" checkout --quiet --detach "$BENCHMARK_ACTUAL_BASE"
    local captured
    captured=$(cd "$BENCHMARK_FIXTURE" && bash "$BENCHMARK_REVISIONS" push "$BENCHMARK_PR_HEAD")
    assert_contains "HEAD_SHA=$BENCHMARK_ACTUAL_BASE" "$captured"
    assert_contains "BASE_SHA=$BENCHMARK_STALE_BASE" "$captured"
}

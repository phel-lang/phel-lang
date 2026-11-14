#!/usr/bin/env bash

# Prints `affected=true` when the diff between two revisions can change a
# benchmark result, `affected=false` otherwise. Anything outside these paths
# (docs, the changelog, agent rules, other workflows) cannot move a number.

set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo 'Usage: benchmark-affected.sh <base-sha> <head-sha>' >&2
    exit 1
fi

benchmark_inputs='^(src/|tests/php/Benchmark/|phpbench\.json$|composer\.(json|lock)$|\.github/workflows/tests\.yml$|\.github/actions/setup-phel/|tools/benchmark-revisions\.sh$|tools/benchmark-affected\.sh$)'

changed=$(git diff --name-only "$1" "$2")

if grep -Eq "$benchmark_inputs" <<< "$changed"; then
    echo 'affected=true'
else
    echo 'affected=false'
fi

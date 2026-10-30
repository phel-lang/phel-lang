#!/usr/bin/env bash

set -euo pipefail

if [[ $# -lt 2 || $# -gt 3 ]]; then
    echo 'Usage: benchmark-revisions.sh <event> <event-sha> [pr-head-sha]' >&2
    exit 1
fi

head_sha=$(git rev-parse --verify 'HEAD^{commit}')
if [[ "$1" == pull_request ]]; then
    read -r base_sha pr_head extra <<< "$(git show -s --format=%P "$head_sha")"
    if [[ "$head_sha" != "$2" || -z "$base_sha" || -z "$pr_head" || -n "$extra" || "$pr_head" != "${3:-}" ]]; then
        echo 'Expected the checked-out PR merge with exactly two parents and the PR head as its second parent.' >&2
        exit 1
    fi
else
    base_sha=$(git rev-parse --verify "${2}^1")
fi

printf 'HEAD_SHA=%s\nBASE_SHA=%s\n' "$head_sha" "$base_sha"

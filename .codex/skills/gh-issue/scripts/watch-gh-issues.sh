#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: watch-gh-issues.sh [--repo DIR] [--interval SECONDS] [--once] [--execute] [--limit N]

Poll open GitHub issues and invoke Codex on the next candidate.

Defaults to dry-run. Pass --execute to run:
  codex exec -C <repo> "Use $gh-issue for #<number>..."

Options:
  --repo DIR            repository to operate in; defaults to current git root
  --interval SECONDS    poll interval; defaults to 900
  --once                run one poll cycle and exit
  --execute             actually invoke Codex
  --limit N             issue list page size; defaults to 20
USAGE
}

die() {
  printf 'error: %s\n' "$*" >&2
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "missing required command: $1"
}

repo=""
interval=900
once=false
execute=false
limit=20

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo)
      repo="${2:-}"
      [[ -n "$repo" ]] || die "--repo requires a directory"
      shift 2
      ;;
    --interval)
      interval="${2:-}"
      [[ "$interval" =~ ^[0-9]+$ ]] || die "--interval requires seconds"
      shift 2
      ;;
    --once)
      once=true
      shift
      ;;
    --execute)
      execute=true
      shift
      ;;
    --limit)
      limit="${2:-}"
      [[ "$limit" =~ ^[0-9]+$ ]] || die "--limit requires a number"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      die "unknown argument: $1"
      ;;
  esac
done

require_cmd gh
require_cmd git

if [[ -z "$repo" ]]; then
  repo="$(git rev-parse --show-toplevel 2>/dev/null)" || die "not inside a git repository; pass --repo"
fi

if [[ "$execute" == true ]]; then
  require_cmd codex
fi

cd "$repo"
repo="$(git rev-parse --show-toplevel 2>/dev/null)" || die "not a git repository: $repo"
lock_dir="$(git rev-parse --git-path codex-gh-issue-watch.lock)"
lock_acquired=false

release_lock() {
  if [[ "$lock_acquired" == true ]]; then
    rm -rf "$lock_dir"
    lock_acquired=false
  fi
}

trap release_lock EXIT INT TERM

poll_once() {
  if ! mkdir "$lock_dir" 2>/dev/null; then
    printf 'another watcher appears to be running: %s\n' "$lock_dir" >&2
    return 0
  fi
  lock_acquired=true

  issue="$(
    gh issue list \
      --state open \
      --limit "$limit" \
      --json number,title \
      --jq 'sort_by(.number) | .[0].number // empty'
  )"

  if [[ -z "$issue" ]]; then
    printf '[%s] no open issues found\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    release_lock
    return 0
  fi

  printf '[%s] next issue: #%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$issue"
  if [[ "$execute" != true ]]; then
    printf 'dry-run: pass --execute to run Codex for #%s\n' "$issue"
    release_lock
    return 0
  fi

  codex exec \
    -C "$repo" \
    --sandbox danger-full-access \
    --ask-for-approval never \
    "Use \$gh-issue for #$issue. Follow the skill completely: fetch the issue and comments, assign the author when possible, branch from fresh main, plan, use TDD, commit by context, add a final refactor commit, open a PR, make CI green, merge when allowed, update local main, then stop."
  release_lock
}

while true; do
  poll_once
  if [[ "$once" == true ]]; then
    exit 0
  fi
  sleep "$interval"
done

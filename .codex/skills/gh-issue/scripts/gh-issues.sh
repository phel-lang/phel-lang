#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: gh-issues.sh [--repo DIR] [--interval SECONDS] [--once] [--execute] [--limit N]

Poll open GitHub issues and invoke Codex on the next candidate.

Defaults to dry-run. Pass --execute to run:
  codex exec -C <repo> "Use $gh-issue for #<number>..."

Options:
  --repo DIR            repository to operate in; defaults to current git root
  --interval SECONDS    idle poll interval; defaults to 900
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

sync_main() {
  if [[ -n "$(git status --porcelain)" ]]; then
    die "worktree is dirty; cannot sync main before polling"
  fi

  git switch --quiet main
  git pull --ff-only --prune
}

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

  current_user="$(gh api user --jq '.login')"
  [[ -n "$current_user" ]] || die "could not determine current GitHub user"

  issue="$(
    gh issue list \
      --state open \
      --limit "$limit" \
      --json number,title,assignees \
      --jq "map(select((.assignees | length) == 0 or any(.assignees[]; .login == \"$current_user\"))) | sort_by(.number) | .[0].number // empty"
  )"

  if [[ -z "$issue" ]]; then
    printf '[%s] no unassigned issues or issues assigned to %s found\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$current_user"
    release_lock
    return 1
  fi

  printf '[%s] next issue: #%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$issue"
  if [[ "$execute" != true ]]; then
    printf 'dry-run: pass --execute to run Codex for #%s\n' "$issue"
    release_lock
    return 1
  fi

  if ! codex exec \
    -C "$repo" \
    --dangerously-bypass-approvals-and-sandbox \
    "Use \$gh-issue for #$issue. Follow the skill completely: fetch the issue and comments, assign the authenticated gh user running the script when possible, branch from fresh main, plan, use TDD, commit by context, add a final refactor commit, open a PR, make CI green, merge when allowed, update local main, then stop.

Watcher-mode overrides:
- Run focused local tests while iterating, but do not run the full composer test gate inside this nested Codex process. Use GitHub CI as the full quality gate after opening the PR.
- Create commits with git commit --no-verify after the focused tests pass. The nested Codex process can hang in the local commit-time PHPUnit gate, so watcher mode must not invoke that hook path.
- If CI is green and the only remaining merge blocker is a required review, use admin merge bypass when the authenticated GitHub user has permission."; then
    release_lock
    return 2
  fi

  release_lock
  return 0
}

while true; do
  poll_status=0
  poll_once || poll_status=$?

  case "$poll_status" in
    0)
      sync_main
      if [[ "$once" == true ]]; then
        exit 0
      fi
      continue
      ;;
    1)
      if [[ "$once" == true ]]; then
        exit 0
      fi

      sync_main
      printf '[%s] sleeping for %s seconds before polling again\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$interval"
      sleep "$interval"
      ;;
    *)
      die "Codex issue processing failed; watcher stopped"
      ;;
  esac
done

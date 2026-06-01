#!/usr/bin/env bash
set -uo pipefail

# After a phel-lang release, walks every sibling repo under the ecosystem root
# that depends on phel-lang/phel-lang and asks Claude Code (headless `-p` mode)
# to bump the constraint, refresh deps, and run tests. On success the wrapper
# commits on a chore/bump-phel-X.Y.Z branch, pushes to origin, and opens a PR
# via `gh pr create` (assignee: @me, label: dependencies).

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_DIR="$SCRIPT_DIR/.upgrade-logs"

# Pure helpers live in a sibling lib so bashunit can source them.
# shellcheck source=./upgrade-ecosystem-lib.sh
source "$SCRIPT_DIR/upgrade-ecosystem-lib.sh"

# Default ecosystem root = parent of this phel-lang repo (sibling layout):
#   <root>/phel-lang/  <-- this repo
#   <root>/phel-log/   <-- sibling ecosystem repos
# Override via --root=PATH or PHEL_ECOSYSTEM_ROOT env var.
DEFAULT_ROOT="$(dirname "$REPO_ROOT")"
# Repos to skip are derived dynamically:
#   - the phel-lang repo itself (detected by path == this REPO_ROOT)
#   - archived repos in the phel-lang org (queried once via gh)
#   - anything not depending on phel-lang/phel-lang in composer.json require
# Use --skip=a,b,c for ad-hoc additions.

VERSION=""
ONLY=""
SKIP=""
ROOT="${PHEL_ECOSYSTEM_ROOT:-$DEFAULT_ROOT}"
DRY_RUN=0
YES=0
DIRECT_PUSH=0
FORCE=0
UNSAFE=0
PARALLEL="${PHEL_UPGRADE_PARALLEL:-1}"
CLAUDE_TIMEOUT="${PHEL_UPGRADE_TIMEOUT:-900}"   # seconds; default 15 min
HEARTBEAT_EVERY=60                               # seconds between progress pings

usage() {
  cat <<'EOF'
Usage: build/upgrade-ecosystem.sh [options]

Iterates phel-lang ecosystem repos and uses Claude Code to bump the
phel-lang/phel-lang dependency to the latest stable release. On success
the script commits, pushes, and opens a PR (assignee @me, label
"dependencies") for each repo.

Options:
  --version=X.Y.Z   Target version (default: latest GitHub release tag)
  --only=a,b,c      Limit to these repo names (comma-separated)
  --skip=a,b,c      Extra repos to skip (added to the built-in skip list)
  --root=PATH       Ecosystem root containing sibling repos (default:
                    $PHEL_ECOSYSTEM_ROOT or parent dir of this phel-lang repo)
  --dry-run         Print the plan, do not invoke claude or touch git
  --yes             Required for real runs (safety gate before invoking claude)
  --direct-push     Commit and push directly to the default branch (no
                    chore/bump-phel-* branch, no PR). Use with care.
  --force           Override DIRTY / wrong-branch guards: auto-stash and
                    checkout the default branch before processing.
  --unsafe          Use --dangerously-skip-permissions instead of the scoped
                    tool allowlist (fallback if claude refuses too eagerly).
  --parallel=N      Process up to N repos concurrently (default: 1).
  --timeout=SECS    Per-repo claude timeout (default: 900; env PHEL_UPGRADE_TIMEOUT).
  -h, --help        Show this help

Examples:
  build/upgrade-ecosystem.sh --dry-run
  build/upgrade-ecosystem.sh --only=phel-log --yes
  build/upgrade-ecosystem.sh --version=0.40.0 --yes
  build/upgrade-ecosystem.sh --parallel=4 --yes
  build/upgrade-ecosystem.sh --direct-push --force --yes
EOF
}

log()  { printf '%s\n' "$*" >&2; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

for arg in "$@"; do
  case "$arg" in
    --version=*) VERSION="${arg#*=}" ;;
    --only=*)    ONLY="${arg#*=}" ;;
    --skip=*)    SKIP="${arg#*=}" ;;
    --root=*)    ROOT="${arg#*=}" ;;
    --dry-run)   DRY_RUN=1 ;;
    --yes)       YES=1 ;;
    --direct-push) DIRECT_PUSH=1 ;;
    --force)     FORCE=1 ;;
    --unsafe)    UNSAFE=1 ;;
    --parallel=*)  PARALLEL="${arg#*=}" ;;
    --timeout=*)   CLAUDE_TIMEOUT="${arg#*=}" ;;
    -h|--help)   usage; exit 0 ;;
    *)           fail "Unknown option: $arg (try --help)" ;;
  esac
done

command -v claude >/dev/null 2>&1 || fail "claude CLI not found in PATH"
command -v gh     >/dev/null 2>&1 || fail "gh CLI not found in PATH"
command -v git    >/dev/null 2>&1 || fail "git not found in PATH"
command -v php    >/dev/null 2>&1 || fail "php not found in PATH"

[[ -d "$ROOT" ]] || fail "Ecosystem root does not exist: $ROOT"

if [[ -z "$VERSION" ]]; then
  VERSION="$(gh release view -R phel-lang/phel-lang --json tagName --jq '.tagName' 2>/dev/null || true)"
  [[ -n "$VERSION" ]] || fail "Could not resolve latest phel-lang release; pass --version=X.Y.Z"
fi
VERSION="$(normalize_version "$VERSION")"
CARET="$(derive_caret "$VERSION")"

ARCHIVED="$(gh repo list phel-lang --limit 100 --json name,isArchived \
            --jq '.[] | select(.isArchived) | .name' 2>/dev/null \
            | paste -sd, - || true)"

EFFECTIVE_SKIP=""
[[ -n "$ARCHIVED" ]] && EFFECTIVE_SKIP="$ARCHIVED"
[[ -n "$SKIP"     ]] && EFFECTIVE_SKIP="${EFFECTIVE_SKIP:+$EFFECTIVE_SKIP,}$SKIP"

discover_repos() {
  local out=() abs
  for d in "$ROOT"/*/; do
    [[ -d "$d" ]] || continue
    abs="$(cd "$d" && pwd)"
    # Skip this phel-lang repo itself (we're the upgrader, not the upgradee)
    [[ "$abs" == "$REPO_ROOT" ]] && continue
    local name
    name="$(basename "$d")"
    in_csv "$name" "$EFFECTIVE_SKIP" && continue
    if [[ -n "$ONLY" ]] && ! in_csv "$name" "$ONLY"; then
      continue
    fi
    composer_requires_phel "$d/composer.json" || continue
    out+=("$name")
  done
  (( ${#out[@]} > 0 )) && printf '%s\n' "${out[@]}"
}

REPOS=()
while IFS= read -r _line; do
  [[ -n "$_line" ]] && REPOS+=("$_line")
done < <(discover_repos)

BRANCH="chore/bump-phel-${VERSION}"
COMMIT_MSG="chore: bump phel-lang to ${VERSION}"
PR_TITLE="$COMMIT_MSG"
PR_BODY_PREVIEW="Automated bump of \`phel-lang/phel-lang\` to ${VERSION} via build/upgrade-ecosystem.sh from the phel-lang repo. Tests passed locally before push."
SYSTEM_HINT="You are upgrading a phel-lang ecosystem repo. Be terse. Do not commit, push, or open PRs -- the wrapper script handles git. Stop when tests pass or a real blocker is hit."

# Scoped tool allowlist instead of --dangerously-skip-permissions. Anything not
# listed will cause claude to refuse the tool call in headless mode. Covers the
# bump workflow: read+edit composer.json, run composer/tests, read-only git for
# diagnostics, WebFetch for the CHANGELOG.
ALLOWED_TOOLS="Read Edit Write Glob Grep Bash(composer *) Bash(./vendor/bin/* *) Bash(php *) Bash(git status*) Bash(git diff*) Bash(git log*) Bash(git show*) Bash(ls *) Bash(find *) WebFetch"

if (( UNSAFE )); then
  CLAUDE_PERM_ARGS=(--dangerously-skip-permissions)
  PERM_LABEL="DANGEROUS (--dangerously-skip-permissions)"
else
  CLAUDE_PERM_ARGS=(--allowedTools "$ALLOWED_TOOLS")
  PERM_LABEL="scoped allowlist"
fi

read -r -d '' PROMPT <<EOF || true
Upgrade phel-lang/phel-lang to ${VERSION} in this repository.

Steps:
1. Update the constraint in composer.json. Prefer the caret style ${CARET} unless the
   existing constraint is intentionally broader (e.g. ">=X <Y") -- in that case widen
   the upper bound but keep the existing style.
2. Run: composer update phel-lang/phel-lang --with-all-dependencies
3. Skim the phel-lang CHANGELOG for breaking changes between the previous and new tag
   (https://github.com/phel-lang/phel-lang/blob/main/CHANGELOG.md) and adapt source
   or tests as needed.
4. Run the repo's test command. Try these in order, use the first that exists:
     composer test
     ./vendor/bin/phpunit
     ./vendor/bin/phel test
5. If tests fail, attempt minimal fixes and re-run. Do not rewrite unrelated code.
6. Stop. Do NOT commit, push, tag, or open a PR -- the wrapper handles git.

At the end, print a one-paragraph summary:
  - version before -> after
  - files touched
  - test command run + pass/fail
EOF

log ""
log "==> phel-lang ecosystem upgrade"
log "    target version: $VERSION  (constraint: $CARET)"
log "    root:           $ROOT"
log "    skip:           ${EFFECTIVE_SKIP:-<none>}"
if (( DIRECT_PUSH )); then
  log "    push strategy:  DIRECT (commit + push to default branch, no PR)"
else
  log "    push strategy:  branch + PR"
  log "    branch:         $BRANCH"
  log "    PR title:       $PR_TITLE"
  log "    PR assignee:    @me"
  log "    PR label:       dependencies"
fi
log "    commit msg:     $COMMIT_MSG"
log "    permissions:    $PERM_LABEL"
log "    parallelism:    $PARALLEL"
log "    claude timeout: ${CLAUDE_TIMEOUT}s"
log "    force:          $( ((FORCE)) && echo yes || echo no )"
log "    repos eligible: ${#REPOS[@]}"

if (( DRY_RUN )); then
  log ""
  log "==> Per-repo preflight (no side effects):"
  if (( ${#REPOS[@]} == 0 )); then
    log "    <none>"
  else
    printf '%-28s %-12s %s\n' "REPO" "PREFLIGHT" "DETAIL" >&2
    for name in "${REPOS[@]}"; do
      local_path="$ROOT/$name"
      if ! git -C "$local_path" rev-parse --git-dir >/dev/null 2>&1; then
        printf '%-28s %-12s %s\n' "$name" "SKIP" "not a git repo" >&2
        continue
      fi
      cur="$(git -C "$local_path" rev-parse --abbrev-ref HEAD 2>/dev/null)"
      def="$(git -C "$local_path" symbolic-ref --short refs/remotes/origin/HEAD 2>/dev/null | sed 's@^origin/@@')"
      [[ -n "$def" ]] || def="main"
      dirty="clean"
      [[ -n "$(git -C "$local_path" status --porcelain 2>/dev/null)" ]] && dirty="dirty"
      cur_constraint="$(composer_phel_constraint "$local_path/composer.json")"
      if [[ "$dirty" == "dirty" ]]; then
        if (( FORCE )); then
          printf '%-28s %-12s %s\n' "$name" "WOULD-RUN" "DIRTY -> auto-stash, $cur_constraint -> $CARET" >&2
        else
          printf '%-28s %-12s %s\n' "$name" "WOULD-SKIP" "working tree dirty (branch=$cur, use --force)" >&2
        fi
      elif [[ "$cur" != "$def" ]]; then
        if (( FORCE )); then
          printf '%-28s %-12s %s\n' "$name" "WOULD-RUN" "on $cur -> auto-checkout $def, $cur_constraint -> $CARET" >&2
        else
          printf '%-28s %-12s %s\n' "$name" "WOULD-SKIP" "on $cur, expected $def (use --force)" >&2
        fi
      else
        printf '%-28s %-12s %s\n' "$name" "WOULD-RUN" "$cur_constraint -> $CARET" >&2
      fi
    done
  fi

  log ""
  log "==> Per repo, the wrapper would:"
  log "      1. git fetch origin <default-branch> && git pull --ff-only"
  if (( DIRECT_PUSH )); then
    log "      2. stay on default branch (no branch creation)"
    if (( UNSAFE )); then
      log "      3. claude -p --dangerously-skip-permissions --add-dir <repo> <PROMPT>"
    else
      log "      3. claude -p --allowedTools \"<scoped list>\" --add-dir <repo> <PROMPT>"
    fi
    log "      4. git add -A && git commit -m \"$COMMIT_MSG\""
    log "      5. git push origin <default-branch>"
    log "      (no PR created -- --direct-push)"
  else
    log "      2. git checkout -B $BRANCH  (from default branch)"
    if (( UNSAFE )); then
      log "      3. claude -p --dangerously-skip-permissions --add-dir <repo> <PROMPT>"
    else
      log "      3. claude -p --allowedTools \"<scoped list>\" --add-dir <repo> <PROMPT>"
    fi
    log "      4. git add -A && git commit -m \"$COMMIT_MSG\""
    log "      5. git push -u origin $BRANCH"
    log "      6. gh pr create --assignee @me --label dependencies --title \"$PR_TITLE\" --body \"...\""
  fi
  log ""
  if (( UNSAFE )); then
    log "==> Claude permission mode: --dangerously-skip-permissions (--unsafe)"
  else
    log "==> Claude tool allowlist (no --dangerously-skip-permissions):"
    log "    $ALLOWED_TOOLS"
  fi
  log ""
  log "==> Claude prompt that would be sent:"
  log "----------------------------------------------------------------"
  printf '%s\n' "$PROMPT" >&2
  log "----------------------------------------------------------------"
  log ""
  log "==> dry-run; no side effects. Re-run with --yes to execute."
  exit 0
fi

if (( ! YES )); then
  fail "Refusing to run without --yes (real run will invoke claude, run composer, and push to remote)"
fi

# Validate runtime flags up-front so misconfiguration fails fast, even when
# REPOS is empty (no-op runs should still flag bad --parallel/--timeout).
[[ "$PARALLEL" =~ ^[0-9]+$ ]] && (( PARALLEL >= 1 )) \
  || fail "--parallel must be >= 1 (got: $PARALLEL)"
[[ "$CLAUDE_TIMEOUT" =~ ^[0-9]+$ ]] && (( CLAUDE_TIMEOUT >= 1 )) \
  || fail "--timeout must be a positive integer (got: $CLAUDE_TIMEOUT)"

(( ${#REPOS[@]} > 0 )) || { log "==> nothing to do"; exit 0; }

mkdir -p "$LOG_DIR"
# Result inbox: one file per repo. Avoids contended array writes from
# parallel workers. Read back at end into ordered summary.
RESULT_DIR="$LOG_DIR/.results"
rm -rf "$RESULT_DIR"
mkdir -p "$RESULT_DIR"

# Per-repo worker. Writes one line of $RESULT_DIR/<name> on exit:
#   STATUS|ELAPSED|NOTE
# All stderr from this function is prefixed with [name] for readable
# interleaving when --parallel > 1.
process_repo() {
  local name="$1"
  local REPO_PATH="$ROOT/$name"
  local LOG_FILE="$LOG_DIR/$name.log"
  local T0; T0=$(date +%s)
  local pfx="[$name]"
  local STATUS="" NOTE="" WORK_BRANCH="" DEFAULT_BRANCH="" CURRENT_BRANCH=""

  log "$pfx start  (logfile: $LOG_FILE)"

  if ! git -C "$REPO_PATH" rev-parse --git-dir >/dev/null 2>&1; then
    write_result "$name" SKIPPED 0 "not a git repo"; return
  fi

  DEFAULT_BRANCH="$(git -C "$REPO_PATH" symbolic-ref --short refs/remotes/origin/HEAD 2>/dev/null | sed 's@^origin/@@')"
  [[ -n "$DEFAULT_BRANCH" ]] || DEFAULT_BRANCH="main"
  CURRENT_BRANCH="$(git -C "$REPO_PATH" rev-parse --abbrev-ref HEAD)"

  # Dirty-tree handling
  if [[ -n "$(git -C "$REPO_PATH" status --porcelain)" ]]; then
    if (( FORCE )); then
      log "$pfx --force: stashing local changes"
      if ! git -C "$REPO_PATH" stash push -u -m "upgrade-ecosystem.sh autostash $(date +%s)" >>"$LOG_FILE" 2>&1; then
        write_result "$name" FAIL 0 "git stash failed under --force"; return
      fi
    else
      write_result "$name" DIRTY 0 "working tree dirty (use --force)"; return
    fi
  fi

  # Wrong-branch handling
  if [[ "$CURRENT_BRANCH" != "$DEFAULT_BRANCH" ]]; then
    if (( FORCE )); then
      log "$pfx --force: checking out $DEFAULT_BRANCH (was $CURRENT_BRANCH)"
      git -C "$REPO_PATH" checkout --quiet "$DEFAULT_BRANCH" >>"$LOG_FILE" 2>&1 \
        || { write_result "$name" FAIL 0 "checkout $DEFAULT_BRANCH failed"; return; }
    else
      write_result "$name" SKIPPED 0 "on $CURRENT_BRANCH not $DEFAULT_BRANCH (use --force)"; return
    fi
  fi

  if ! git -C "$REPO_PATH" fetch --quiet origin "$DEFAULT_BRANCH" 2>>"$LOG_FILE"; then
    write_result "$name" FAIL 0 "git fetch failed"; return
  fi
  if ! git -C "$REPO_PATH" pull --ff-only --quiet origin "$DEFAULT_BRANCH" 2>>"$LOG_FILE"; then
    write_result "$name" FAIL 0 "git pull --ff-only failed"; return
  fi

  if (( DIRECT_PUSH )); then
    WORK_BRANCH="$DEFAULT_BRANCH"
  else
    WORK_BRANCH="$BRANCH"
    if git -C "$REPO_PATH" show-ref --verify --quiet "refs/heads/$BRANCH"; then
      git -C "$REPO_PATH" checkout --quiet "$BRANCH"
    else
      git -C "$REPO_PATH" checkout --quiet -b "$BRANCH"
    fi
  fi
  log "$pfx branch: $WORK_BRANCH"

  {
    echo "## $(date '+%Y-%m-%d %H:%M:%S')  $name  target=$VERSION  branch=$WORK_BRANCH"
    echo
  } >"$LOG_FILE"

  # Heartbeat: print elapsed every HEARTBEAT_EVERY seconds while claude runs.
  log "$pfx invoking claude (timeout ${CLAUDE_TIMEOUT}s) ..."
  (
    secs=0
    while sleep "$HEARTBEAT_EVERY"; do
      secs=$((secs + HEARTBEAT_EVERY))
      log "$pfx still running claude (${secs}s)"
    done
  ) &
  local HB_PID=$!

  local claude_rc=0
  ( cd "$REPO_PATH" && run_with_timeout "$CLAUDE_TIMEOUT" \
      claude -p \
        "${CLAUDE_PERM_ARGS[@]}" \
        --add-dir "$REPO_PATH" \
        --append-system-prompt "$SYSTEM_HINT" \
        "$PROMPT" ) >>"$LOG_FILE" 2>&1 || claude_rc=$?

  kill "$HB_PID" 2>/dev/null || true
  wait "$HB_PID" 2>/dev/null || true

  if (( claude_rc == 0 )); then
    STATUS="OK"
  elif (( claude_rc == 124 )); then
    write_result "$name" TIMEOUT $(( $(date +%s) - T0 )) "claude exceeded ${CLAUDE_TIMEOUT}s"; return
  else
    write_result "$name" FAIL $(( $(date +%s) - T0 )) "claude exited $claude_rc"; return
  fi

  # Decide commit/push/PR path
  if [[ -z "$(git -C "$REPO_PATH" status --porcelain)" ]]; then
    write_result "$name" NOOP $(( $(date +%s) - T0 )) "no changes (already up to date)"; return
  fi

  if (( DIRECT_PUSH )); then
    log "$pfx committing + pushing directly to $DEFAULT_BRANCH ..."
    { echo; echo "## wrapper: git commit + direct push to $DEFAULT_BRANCH"; } >>"$LOG_FILE"
    if git -C "$REPO_PATH" add -A >>"$LOG_FILE" 2>&1 \
       && git -C "$REPO_PATH" commit -m "$COMMIT_MSG" >>"$LOG_FILE" 2>&1 \
       && git -C "$REPO_PATH" push origin "$DEFAULT_BRANCH" >>"$LOG_FILE" 2>&1; then
      write_result "$name" OK $(( $(date +%s) - T0 )) "pushed to $DEFAULT_BRANCH"
    else
      write_result "$name" GIT_FAIL $(( $(date +%s) - T0 )) "commit or push to $DEFAULT_BRANCH failed"
    fi
    return
  fi

  log "$pfx committing + pushing + opening PR ..."
  { echo; echo "## wrapper: git commit + push + gh pr create"; } >>"$LOG_FILE"
  if git -C "$REPO_PATH" add -A >>"$LOG_FILE" 2>&1 \
     && git -C "$REPO_PATH" commit -m "$COMMIT_MSG" >>"$LOG_FILE" 2>&1 \
     && git -C "$REPO_PATH" push -u origin "$BRANCH" >>"$LOG_FILE" 2>&1; then

    local PR_BODY="Automated bump of \`phel-lang/phel-lang\` to ${VERSION} via build/upgrade-ecosystem.sh from the phel-lang repo. Tests passed locally before push."
    local PR_URL=""
    # Ensure the label exists; gh pr create aborts the whole PR if --label is missing.
    ( cd "$REPO_PATH" && gh label create dependencies \
        --color 0366d6 --description "Dependency updates" 2>>"$LOG_FILE" ) || true
    # Capture the URL directly from gh pr create's stdout (no race with gh pr view).
    PR_URL="$( cd "$REPO_PATH" && gh pr create \
                 --assignee @me \
                 --label dependencies \
                 --title "$COMMIT_MSG" \
                 --body "$PR_BODY" 2>>"$LOG_FILE" )"
    local gh_rc=$?
    echo "$PR_URL" >>"$LOG_FILE"
    if (( gh_rc == 0 )) && [[ -n "$PR_URL" ]]; then
      write_result "$name" OK $(( $(date +%s) - T0 )) "$PR_URL"
    else
      write_result "$name" PR_FAIL $(( $(date +%s) - T0 )) "commit+push OK, gh pr create failed"
    fi
  else
    write_result "$name" GIT_FAIL $(( $(date +%s) - T0 )) "commit or push failed"
  fi
}

write_result() {
  local name="$1" status="$2" elapsed="$3" note="$4"
  printf '%s|%s|%s\n' "$status" "$elapsed" "$note" > "$RESULT_DIR/$name"
  log "[$name] $status (${elapsed}s)  $note"
}

TOTAL=${#REPOS[@]}
START_TS=$(date +%s)

if (( PARALLEL == 1 )); then
  for name in "${REPOS[@]}"; do
    process_repo "$name"
  done
else
  log ""
  log "==> running $TOTAL repos with parallelism=$PARALLEL"
  pids=()
  for name in "${REPOS[@]}"; do
    process_repo "$name" &
    pids+=($!)
    if (( ${#pids[@]} >= PARALLEL )); then
      # bash 3.2 lacks `wait -n`; drain the batch.
      for p in "${pids[@]}"; do wait "$p" 2>/dev/null || true; done
      pids=()
    fi
  done
  # bash 3.2 + set -u: expanding an empty array is "unbound"; guard the final drain.
  (( ${#pids[@]} )) && for p in "${pids[@]}"; do wait "$p" 2>/dev/null || true; done
fi

# Re-collate results in original REPOS order
declare -a RESULTS
for name in "${REPOS[@]}"; do
  if [[ -f "$RESULT_DIR/$name" ]]; then
    IFS='|' read -r s t n < "$RESULT_DIR/$name"
    RESULTS+=("$name|$s|$t|$n")
  else
    RESULTS+=("$name|FAIL|0|worker produced no result")
  fi
done

END_TS=$(date +%s)
TOTAL_ELAPSED=$((END_TS-START_TS))

log ""
log "==> Summary  (total ${TOTAL_ELAPSED}s)"
printf '%-28s %-10s %6s  %s\n' "REPO" "STATUS" "TIME" "NOTE" >&2
ok=0; noop=0; fail=0; skipped=0; dirty=0; timeout=0
declare -a PR_URLS
if (( ${#RESULTS[@]} > 0 )); then
  for row in "${RESULTS[@]}"; do
    IFS='|' read -r r s t n <<<"$row"
    printf '%-28s %-10s %5ss  %s\n' "$r" "$s" "$t" "$n" >&2
    case "$s" in
      OK)              ok=$((ok+1));     [[ "$n" =~ ^https?:// ]] && PR_URLS+=("$r  $n") ;;
      NOOP)            noop=$((noop+1)) ;;
      TIMEOUT)         timeout=$((timeout+1)); fail=$((fail+1)) ;;
      FAIL|GIT_FAIL|PR_FAIL) fail=$((fail+1)) ;;
      DIRTY)           dirty=$((dirty+1)) ;;
      SKIPPED)         skipped=$((skipped+1)) ;;
    esac
  done
fi
log ""
log "==> $ok OK, $noop NOOP, $fail FAIL ($timeout timeout), $dirty DIRTY, $skipped SKIPPED"

if (( ${#PR_URLS[@]} > 0 )); then
  log ""
  log "==> Pull requests opened:"
  for u in "${PR_URLS[@]}"; do log "    $u"; done
fi

(( fail == 0 ))

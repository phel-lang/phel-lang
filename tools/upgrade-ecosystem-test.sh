#!/usr/bin/env bash

# Bashunit tests for upgrade-ecosystem-lib.sh
# Run with: tools/bashunit build/upgrade-ecosystem-test.sh
#
# Conventions (matching build/release-test.sh):
#   - Assertions that need to inspect a command's exit code use the
#     `local rc=0; cmd || rc=$?; assert_equals "N" "$rc"` pattern. This is the
#     only reliable shape with bashunit's assertions, which read $? rather
#     than executing their arguments.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT="$SCRIPT_DIR/upgrade-ecosystem.sh"

source "$SCRIPT_DIR/upgrade-ecosystem-lib.sh"

TEMP_DIR=""
ORIGINAL_PATH=""

# Install a no-op stub for the named binary in $TEMP_DIR/bin and prepend it
# to PATH. Lets the script's preflight `command -v <bin>` checks pass in
# environments (such as the CI runner) where the real binary is not
# installed. The stub exits 0 immediately; tests never exercise code paths
# that actually invoke claude.
function _stub_bin() {
    local name="$1"
    cat > "$TEMP_DIR/bin/$name" <<'STUB'
#!/usr/bin/env bash
exit 0
STUB
    chmod +x "$TEMP_DIR/bin/$name"
}

function set_up() {
    TEMP_DIR="$(mktemp -d)"
    ORIGINAL_PATH="$PATH"
    mkdir -p "$TEMP_DIR/bin"
    _stub_bin claude
    PATH="$TEMP_DIR/bin:$PATH"
}

function tear_down() {
    PATH="$ORIGINAL_PATH"
    [[ -n "$TEMP_DIR" ]] && rm -rf "$TEMP_DIR"
}

# Run a command, capture exit code without tripping `set -e`.
function _rc() {
    local rc=0
    "$@" >/dev/null 2>&1 || rc=$?
    printf '%s' "$rc"
}

# =============================================================================
# normalize_version
# =============================================================================

function test_normalize_version_strips_leading_v() {
    assert_equals "0.40.0" "$(normalize_version 'v0.40.0')"
}

function test_normalize_version_passthrough_when_no_prefix() {
    assert_equals "0.40.0" "$(normalize_version '0.40.0')"
}

function test_normalize_version_empty() {
    assert_equals "" "$(normalize_version '')"
}

function test_normalize_version_only_strips_one_v() {
    assert_equals "v1.0.0" "$(normalize_version 'vv1.0.0')"
}

# =============================================================================
# derive_caret
# =============================================================================

function test_derive_caret_zero_dot_x() {
    assert_equals "^0.39" "$(derive_caret '0.39.0')"
}

function test_derive_caret_zero_dot_x_with_patch() {
    assert_equals "^0.40" "$(derive_caret '0.40.7')"
}

function test_derive_caret_one_dot_x() {
    assert_equals "^1.2" "$(derive_caret '1.2.3')"
}

function test_derive_caret_two_dot_x() {
    assert_equals "^10.20" "$(derive_caret '10.20.30')"
}

function test_derive_caret_passthrough_when_unparseable() {
    assert_equals "^weird" "$(derive_caret 'weird')"
}

function test_derive_caret_passthrough_two_segments() {
    # 0.40 has no third segment; falls through to passthrough.
    assert_equals "^0.40" "$(derive_caret '0.40')"
}

# =============================================================================
# in_csv
# =============================================================================

function test_in_csv_finds_first_token() {
    assert_equals "0" "$(_rc in_csv foo 'foo,bar,baz')"
}

function test_in_csv_finds_middle_token() {
    assert_equals "0" "$(_rc in_csv bar 'foo,bar,baz')"
}

function test_in_csv_finds_last_token() {
    assert_equals "0" "$(_rc in_csv baz 'foo,bar,baz')"
}

function test_in_csv_rejects_partial_match() {
    # 'fo' is a substring of 'foo' but not a member of the CSV.
    assert_equals "1" "$(_rc in_csv fo 'foo,bar')"
}

function test_in_csv_rejects_missing() {
    assert_equals "1" "$(_rc in_csv nope 'foo,bar')"
}

function test_in_csv_rejects_empty_haystack() {
    assert_equals "1" "$(_rc in_csv anything '')"
}

function test_in_csv_single_element() {
    assert_equals "0" "$(_rc in_csv only 'only')"
}

# =============================================================================
# composer_requires_phel / composer_phel_constraint
# =============================================================================

function _write_composer() {
    # Writes $TEMP_DIR/composer.json with the given JSON content and echoes
    # the path so callers can capture it.
    printf '%s\n' "$1" > "$TEMP_DIR/composer.json"
    printf '%s\n' "$TEMP_DIR/composer.json"
}

function test_composer_requires_phel_true_for_phel_dep() {
    local path
    path="$(_write_composer '{"require":{"phel-lang/phel-lang":"^0.39","php":">=8.4"}}')"
    assert_equals "0" "$(_rc composer_requires_phel "$path")"
}

function test_composer_requires_phel_false_when_only_in_require_dev() {
    # Bumping require-dep is out of scope; the script only looks at "require".
    local path
    path="$(_write_composer '{"require":{"php":">=8.4"},"require-dev":{"phel-lang/phel-lang":"^0.39"}}')"
    assert_equals "1" "$(_rc composer_requires_phel "$path")"
}

function test_composer_requires_phel_false_when_only_in_keywords() {
    local path
    path="$(_write_composer '{"name":"foo/bar","keywords":["phel-lang/phel-lang"]}')"
    assert_equals "1" "$(_rc composer_requires_phel "$path")"
}

function test_composer_requires_phel_false_when_no_require_key() {
    local path
    path="$(_write_composer '{"name":"foo/bar"}')"
    assert_equals "1" "$(_rc composer_requires_phel "$path")"
}

function test_composer_requires_phel_false_when_invalid_json() {
    local path
    path="$(_write_composer 'this is not json {')"
    assert_equals "1" "$(_rc composer_requires_phel "$path")"
}

function test_composer_requires_phel_false_when_file_missing() {
    assert_equals "1" "$(_rc composer_requires_phel "$TEMP_DIR/does-not-exist.json")"
}

function test_composer_phel_constraint_returns_constraint() {
    local path
    path="$(_write_composer '{"require":{"phel-lang/phel-lang":"^0.37"}}')"
    assert_equals "^0.37" "$(composer_phel_constraint "$path")"
}

function test_composer_phel_constraint_returns_question_mark_when_absent() {
    local path
    path="$(_write_composer '{"require":{"php":">=8.4"}}')"
    assert_equals "?" "$(composer_phel_constraint "$path")"
}

function test_composer_phel_constraint_returns_question_mark_when_file_missing() {
    assert_equals "?" "$(composer_phel_constraint "$TEMP_DIR/missing.json")"
}

# =============================================================================
# run_with_timeout
# =============================================================================

function test_run_with_timeout_passes_through_success() {
    assert_equals "0" "$(_rc run_with_timeout 5 true)"
}

function test_run_with_timeout_passes_through_exit_code() {
    # Non-zero exit from the wrapped command is preserved.
    assert_equals "1" "$(_rc run_with_timeout 5 false)"
}

function test_run_with_timeout_kills_on_overrun() {
    # `sleep 5` exceeds the 1s budget -> exit code 124 per coreutils convention.
    assert_equals "124" "$(_rc run_with_timeout 1 sleep 5)"
}

# =============================================================================
# CLI / argument-parsing smoke tests
#
# These shell out to the real script and assert observable behavior. They must
# not invoke claude/gh against a real release -- everything either bails on a
# validation error before network, or uses --dry-run.
# =============================================================================

function test_script_help_exits_zero() {
    local rc=0
    "$SCRIPT" --help >/dev/null 2>&1 || rc=$?
    assert_equals "0" "$rc"
}

function test_script_help_mentions_direct_push() {
    assert_contains "--direct-push" "$($SCRIPT --help 2>&1)"
}

function test_script_help_mentions_parallel() {
    assert_contains "--parallel=N" "$($SCRIPT --help 2>&1)"
}

function test_script_help_mentions_force() {
    assert_contains "--force" "$($SCRIPT --help 2>&1)"
}

function test_script_help_mentions_unsafe() {
    assert_contains "--unsafe" "$($SCRIPT --help 2>&1)"
}

function test_script_unknown_flag_is_rejected() {
    local rc=0
    "$SCRIPT" --no-such-flag >/dev/null 2>&1 || rc=$?
    assert_equals "1" "$rc"
}

function test_script_real_run_refuses_without_yes() {
    # No --yes => must refuse before doing anything irreversible.
    local out
    out="$($SCRIPT --version=0.40.0 2>&1 || true)"
    assert_contains "Refusing to run without --yes" "$out"
}

function test_script_dry_run_does_not_require_yes() {
    local rc=0
    "$SCRIPT" --dry-run --version=0.40.0 --only=__none__ >/dev/null 2>&1 || rc=$?
    assert_equals "0" "$rc"
}

function test_script_dry_run_reports_target_version() {
    local out
    out="$($SCRIPT --dry-run --version=v0.41.0 --only=__none__ 2>&1)"
    # v-prefix is normalized away before display.
    assert_contains "target version: 0.41.0" "$out"
    assert_contains "constraint: ^0.41" "$out"
}

function test_script_dry_run_shows_force_flag() {
    local out
    out="$($SCRIPT --dry-run --version=0.40.0 --force --only=__none__ 2>&1)"
    assert_contains "force:          yes" "$out"
}

function test_script_dry_run_shows_unsafe_perm_mode() {
    local out
    out="$($SCRIPT --dry-run --version=0.40.0 --unsafe --only=__none__ 2>&1)"
    assert_contains "DANGEROUS (--dangerously-skip-permissions)" "$out"
}

function test_script_dry_run_shows_safe_perm_mode_by_default() {
    local out
    out="$($SCRIPT --dry-run --version=0.40.0 --only=__none__ 2>&1)"
    assert_contains "scoped allowlist" "$out"
}

function test_script_dry_run_shows_parallel_setting() {
    local out
    out="$($SCRIPT --dry-run --version=0.40.0 --parallel=7 --only=__none__ 2>&1)"
    assert_contains "parallelism:    7" "$out"
}

function test_script_dry_run_shows_custom_timeout() {
    local out
    out="$($SCRIPT --dry-run --version=0.40.0 --timeout=123 --only=__none__ 2>&1)"
    assert_contains "claude timeout: 123s" "$out"
}

function test_script_dry_run_shows_direct_push_strategy() {
    local out
    out="$($SCRIPT --dry-run --version=0.40.0 --direct-push --only=__none__ 2>&1)"
    assert_contains "DIRECT (commit + push to default branch, no PR)" "$out"
}

function test_script_rejects_zero_parallel() {
    # The validation gate sits between dry-run and the loop; confirm it
    # rejects --parallel=0 rather than silently treating it as 1.
    local out
    out="$($SCRIPT --version=0.40.0 --parallel=0 --yes --only=__none__ 2>&1 || true)"
    assert_contains "--parallel must be >= 1" "$out"
}

# =============================================================================
# repo_slug_from_url
# =============================================================================

function test_repo_slug_from_ssh_url() {
    assert_equals "phel-lang/phel-log" "$(repo_slug_from_url 'git@github.com:phel-lang/phel-log.git')"
}

function test_repo_slug_from_https_url_with_git_suffix() {
    assert_equals "phel-lang/phel-log" "$(repo_slug_from_url 'https://github.com/phel-lang/phel-log.git')"
}

function test_repo_slug_from_https_url_without_suffix() {
    assert_equals "phel-lang/clojure-test-suite" "$(repo_slug_from_url 'https://github.com/phel-lang/clojure-test-suite')"
}

function test_repo_slug_from_ssh_scheme_url() {
    assert_equals "phel-lang/phel-log" "$(repo_slug_from_url 'ssh://git@github.com/phel-lang/phel-log.git')"
}

function test_repo_slug_keeps_dots_inside_the_name() {
    assert_equals "phel-lang/phel-lang.org" "$(repo_slug_from_url 'git@github.com:phel-lang/phel-lang.org.git')"
}

function test_repo_slug_is_empty_for_a_non_github_remote() {
    assert_empty "$(repo_slug_from_url '/srv/git/phel-log.git')"
}

function test_repo_slug_from_ssh_url_with_port() {
    assert_equals "phel-lang/phel-log" "$(repo_slug_from_url 'ssh://git@github.com:22/phel-lang/phel-log.git')"
}

function test_repo_slug_rejects_a_lookalike_host() {
    assert_empty "$(repo_slug_from_url 'https://notgithub.com/phel-lang/phel-log.git')"
}

function test_repo_slug_rejects_github_com_inside_the_path() {
    assert_empty "$(repo_slug_from_url 'https://mirror.example/github.com/phel-lang/phel-log.git')"
}

# =============================================================================
# real run: an origin the script cannot trust is refused before any mutation
# =============================================================================

# One ecosystem repo under $TEMP_DIR/eco/demo, on main, clean, requiring phel,
# with origin set to $1. claude and gh are stubs; claude leaves a marker.
function _make_eco_repo() {
    local origin="$1"
    local repo="$TEMP_DIR/eco/demo"
    mkdir -p "$repo"
    printf '[user]\n\tname = Test\n\temail = test@example.com\n[commit]\n\tgpgsign = false\n[init]\n\tdefaultBranch = main\n' \
        > "$TEMP_DIR/gitconfig"
    export GIT_CONFIG_GLOBAL="$TEMP_DIR/gitconfig"
    export GIT_CONFIG_NOSYSTEM=1
    echo '{"require": {"phel-lang/phel-lang": "^0.52"}}' > "$repo/composer.json"
    git -C "$repo" init -q
    git -C "$repo" add -A
    git -C "$repo" commit -q -m init
    git -C "$repo" remote add origin "$origin"
    printf '#!/usr/bin/env bash\ntouch "%s/claude-ran"\n' "$TEMP_DIR" > "$TEMP_DIR/bin/claude"
    chmod +x "$TEMP_DIR/bin/claude"
    _stub_bin gh
}

function _run_real() {
    "$SCRIPT" --root="$TEMP_DIR/eco" --only=demo --version=0.53.0 --yes 2>&1 || true
}

function test_script_skips_a_non_github_origin_before_touching_the_repo() {
    _make_eco_repo "$TEMP_DIR/elsewhere.git"

    local out
    out="$(_run_real)"

    assert_contains "origin is not a github.com URL" "$out"
    assert_file_not_exists "$TEMP_DIR/claude-ran"
    assert_equals "main" "$(git -C "$TEMP_DIR/eco/demo" rev-parse --abbrev-ref HEAD)"
    assert_empty "$(git -C "$TEMP_DIR/eco/demo" status --porcelain)"
    unset GIT_CONFIG_GLOBAL GIT_CONFIG_NOSYSTEM
}

function test_script_skips_an_origin_with_two_push_urls() {
    _make_eco_repo "git@github.com:phel-lang/demo.git"
    git -C "$TEMP_DIR/eco/demo" remote set-url --add --push origin "git@github.com:phel-lang/demo.git"
    git -C "$TEMP_DIR/eco/demo" remote set-url --add --push origin "git@github.com:someone/else.git"

    local out
    out="$(_run_real)"

    assert_contains "origin needs exactly one push URL" "$out"
    assert_file_not_exists "$TEMP_DIR/claude-ran"
    unset GIT_CONFIG_GLOBAL GIT_CONFIG_NOSYSTEM
}

function test_script_skips_an_origin_that_pushes_elsewhere() {
    _make_eco_repo "git@github.com:phel-lang/demo.git"
    git -C "$TEMP_DIR/eco/demo" remote set-url --push origin "git@github.com:someone/fork.git"

    local out
    out="$(_run_real)"

    assert_contains "origin fetches phel-lang/demo but pushes to someone/fork" "$out"
    assert_file_not_exists "$TEMP_DIR/claude-ran"
    unset GIT_CONFIG_GLOBAL GIT_CONFIG_NOSYSTEM
}

function test_script_direct_push_accepts_a_non_github_origin() {
    git init -q --bare "$TEMP_DIR/elsewhere.git"
    _make_eco_repo "$TEMP_DIR/elsewhere.git"
    git -C "$TEMP_DIR/eco/demo" push -q origin main 2>/dev/null
    git -C "$TEMP_DIR/eco/demo" remote set-head origin main 2>/dev/null

    local out
    out="$("$SCRIPT" --root="$TEMP_DIR/eco" --only=demo --version=0.53.0 --yes --direct-push 2>&1 || true)"

    assert_not_contains "origin is not a github.com URL" "$out"
    assert_file_exists "$TEMP_DIR/claude-ran"
    unset GIT_CONFIG_GLOBAL GIT_CONFIG_NOSYSTEM
}

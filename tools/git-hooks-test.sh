#!/usr/bin/env bash

# Bashunit tests for tools/git-hooks: the agent-config resync must run after
# every way a pull, a rebase or a branch switch can bring in reviewed spec
# changes, only then, and never run code taken from a checked-out branch.
# Run with: tools/bashunit tools/git-hooks-test.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOOKS_SRC="$SCRIPT_DIR/git-hooks"

TEMP_ROOT=""
TEMP_DIR=""
ORIGINAL_PATH=""

function set_up() {
    TEMP_ROOT="$(cd "${TMPDIR:-/tmp}" && pwd -P)"
    TEMP_DIR="$(mktemp -d "$TEMP_ROOT/git-hooks-test.XXXXXX")"
    ORIGINAL_PATH="$PATH"

    # agnostic-ai stub: records each call instead of syncing.
    mkdir -p "$TEMP_DIR/bin"
    cat > "$TEMP_DIR/bin/agnostic-ai" <<STUB
#!/usr/bin/env bash
echo "\$*" >> "$TEMP_DIR/sync.log"
STUB
    chmod +x "$TEMP_DIR/bin/agnostic-ai"
    PATH="$TEMP_DIR/bin:$PATH"

    # Isolate from the developer's git config (signing, global hooks, pull mode).
    printf '[user]\n\tname = Test\n\temail = test@example.com\n[commit]\n\tgpgsign = false\n[init]\n\tdefaultBranch = main\n' \
        > "$TEMP_DIR/gitconfig"
    export GIT_CONFIG_GLOBAL="$TEMP_DIR/gitconfig"
    export GIT_CONFIG_NOSYSTEM=1

    _make_origin
    git clone -q "$TEMP_DIR/origin" "$TEMP_DIR/work"
    (cd "$TEMP_DIR/work" && ./tools/git-hooks/init.sh >/dev/null)
    # First run catches the fresh clone up; the tests start from a synced state.
    (cd "$TEMP_DIR/work" && .git/hooks/sync-agent-config >/dev/null 2>&1)
    : > "$TEMP_DIR/sync.log"
}

function tear_down() {
    PATH="$ORIGINAL_PATH"
    unset GIT_CONFIG_GLOBAL GIT_CONFIG_NOSYSTEM
    # Only ever delete the directory this test created under the temp root.
    if [[ -n "$TEMP_DIR" && "$TEMP_DIR" == "$TEMP_ROOT/git-hooks-test."* ]]; then
        rm -rf "$TEMP_DIR"
    fi
}

function _make_origin() {
    local origin="$TEMP_DIR/origin"
    mkdir -p "$origin/.agnostic-ai/rules" "$origin/tools"
    cp -R "$HOOKS_SRC" "$origin/tools/git-hooks"
    echo "rule" > "$origin/.agnostic-ai/rules/a.md"
    echo "code" > "$origin/code.txt"
    git -C "$origin" init -q
    git -C "$origin" add -A
    git -C "$origin" commit -q -m "init"
}

function _commit_in() {
    local repo="$1" file="$2" content="$3"
    echo "$content" >> "$repo/$file"
    git -C "$repo" add "$file"
    git -C "$repo" commit -q -m "change $file"
}

function _sync_count() {
    [[ -f "$TEMP_DIR/sync.log" ]] || { printf '0'; return; }
    wc -l < "$TEMP_DIR/sync.log" | tr -d ' '
}

function _git() {
    git -C "$TEMP_DIR/work" "$@" >/dev/null 2>&1
}

function _forget_last_sync() {
    rm -f "$(git -C "$TEMP_DIR/work" rev-parse --absolute-git-dir)/agnostic-ai-synced"
}

# --- when it syncs -----------------------------------------------------------

function test_merge_pull_with_spec_change_syncs() {
    _commit_in "$TEMP_DIR/origin" .agnostic-ai/rules/a.md "more"

    _git pull -q --no-rebase

    assert_equals "1" "$(_sync_count)"
}

function test_rebase_pull_with_spec_change_syncs_once() {
    _commit_in "$TEMP_DIR/work" code.txt "local"
    _commit_in "$TEMP_DIR/origin" .agnostic-ai/rules/a.md "more"

    _git pull -q --rebase

    assert_equals "1" "$(_sync_count)"
}

function test_switching_to_a_branch_with_newer_reviewed_specs_syncs() {
    _commit_in "$TEMP_DIR/origin" .agnostic-ai/rules/a.md "more"
    _git fetch -q

    _git checkout -q -b fresh origin/main

    assert_equals "1" "$(_sync_count)"
}

function test_fresh_install_catches_up_stale_output() {
    _forget_last_sync

    _git checkout -q -b any

    assert_equals "1" "$(_sync_count)"
}

# --- when it does not --------------------------------------------------------

function test_pull_without_spec_change_does_not_sync() {
    _commit_in "$TEMP_DIR/origin" code.txt "more"

    _git pull -q --no-rebase

    assert_equals "0" "$(_sync_count)"
}

function test_already_synced_specs_do_not_sync_again() {
    _commit_in "$TEMP_DIR/origin" .agnostic-ai/rules/a.md "more"
    _git pull -q --no-rebase

    _git checkout -q -b same-specs

    assert_equals "1" "$(_sync_count)"
}

function test_amend_does_not_sync() {
    _commit_in "$TEMP_DIR/work" .agnostic-ai/rules/a.md "more"

    _git commit -q --amend -m "amended"

    assert_equals "0" "$(_sync_count)"
}

function test_missing_agnostic_ai_never_fails_the_pull() {
    rm "$TEMP_DIR/bin/agnostic-ai"
    PATH="$TEMP_DIR/bin:/usr/bin:/bin"
    _commit_in "$TEMP_DIR/origin" .agnostic-ai/rules/a.md "more"

    local rc=0
    git -C "$TEMP_DIR/work" pull -q --no-rebase >/dev/null 2>&1 || rc=$?

    assert_equals "0" "$rc"
}

# --- trust -------------------------------------------------------------------

function test_branch_with_unreviewed_specs_does_not_sync() {
    _git checkout -q -b other
    _commit_in "$TEMP_DIR/work" .agnostic-ai/rules/a.md "more"
    _git checkout -q main
    : > "$TEMP_DIR/sync.log"

    _git checkout -q other

    assert_equals "0" "$(_sync_count)"
}

function test_checked_out_branch_cannot_change_the_hook_that_runs() {
    _git checkout -q -b evil
    local hook
    for hook in post-checkout post-merge post-rewrite sync-agent-config; do
        printf '#!/bin/bash\ntouch "%s/pwned"\n' "$TEMP_DIR" > "$TEMP_DIR/work/tools/git-hooks/$hook.sh"
    done
    echo "more" >> "$TEMP_DIR/work/.agnostic-ai/rules/a.md"
    _git add -A
    _git commit -q -m "evil hooks"
    _git checkout -q main

    _git checkout -q evil
    _git checkout -q main

    assert_file_not_exists "$TEMP_DIR/pwned"
}

function test_untracked_spec_file_blocks_the_sync() {
    echo "local" > "$TEMP_DIR/work/.agnostic-ai/rules/untracked.md"
    _commit_in "$TEMP_DIR/origin" .agnostic-ai/rules/a.md "more"

    _git pull -q --no-rebase

    assert_equals "0" "$(_sync_count)"
}

function test_modified_spec_file_blocks_the_sync() {
    echo "local edit" >> "$TEMP_DIR/work/.agnostic-ai/rules/a.md"
    _forget_last_sync

    _git checkout -q -b elsewhere

    assert_equals "0" "$(_sync_count)"
}

function test_branch_tracking_a_local_layer_blocks_the_sync() {
    _git checkout -q -b with-local
    echo "overlay: evil" > "$TEMP_DIR/work/agnostic-ai.local.yaml"
    _git add -f agnostic-ai.local.yaml
    _git commit -q -m "track a local layer"
    _git checkout -q main
    _forget_last_sync
    : > "$TEMP_DIR/sync.log"

    _git checkout -q with-local

    assert_equals "0" "$(_sync_count)"
}

# --- installer ---------------------------------------------------------------

function test_init_leaves_foreign_hooks_alone() {
    local hooks="$TEMP_DIR/work/.git/hooks"
    rm -f "$hooks/post-merge" "$hooks/pre-commit"
    printf '#!/bin/bash\necho lfs\n' > "$hooks/post-merge"
    printf '#!/bin/bash\necho lint\n' > "$hooks/pre-commit"

    (cd "$TEMP_DIR/work" && ./tools/git-hooks/init.sh >/dev/null 2>&1)

    assert_equals "echo lfs" "$(tail -1 "$hooks/post-merge")"
    assert_equals "echo lint" "$(tail -1 "$hooks/pre-commit")"
}

function test_init_is_idempotent_for_its_own_hooks() {
    (cd "$TEMP_DIR/work" && ./tools/git-hooks/init.sh >/dev/null 2>&1)
    _commit_in "$TEMP_DIR/origin" .agnostic-ai/rules/a.md "more"

    _git pull -q --no-rebase

    assert_equals "1" "$(_sync_count)"
}

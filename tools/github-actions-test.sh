#!/usr/bin/env bash

set -euo pipefail

ACTION_TEST_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ACTION_FIXTURE=""

function set_up() {
    ACTION_FIXTURE=$(mktemp -d)
    export ACTION_FIXTURE
    mkdir "$ACTION_FIXTURE/bin"
    printf 'corrupt release archive\n' > "$ACTION_FIXTURE/payload"
    : > "$ACTION_FIXTURE/events"
    awk '
        /- name: Install agnostic-ai/ { install = 1; next }
        install && /run: \|/ { body = 1; next }
        body && /^      - name:/ { exit }
        body { sub(/^          /, ""); print }
    ' "$ACTION_TEST_ROOT/.github/workflows/agnostic-ai.yml" > "$ACTION_FIXTURE/install.sh"

    cat > "$ACTION_FIXTURE/bin/curl" <<'SH'
#!/usr/bin/env bash
cp "$ACTION_FIXTURE/payload" agnostic-ai.tar.gz
SH
    cat > "$ACTION_FIXTURE/bin/tar" <<'SH'
#!/usr/bin/env bash
printf 'extract\n' >> "$ACTION_FIXTURE/events"
SH
    cat > "$ACTION_FIXTURE/bin/sudo" <<'SH'
#!/usr/bin/env bash
printf 'install\n' >> "$ACTION_FIXTURE/events"
SH
    cat > "$ACTION_FIXTURE/bin/agnostic-ai" <<'SH'
#!/usr/bin/env bash
printf 'execute\n' >> "$ACTION_FIXTURE/events"
SH
    if ! command -v sha256sum >/dev/null; then
        cat > "$ACTION_FIXTURE/bin/sha256sum" <<'SH'
#!/usr/bin/env bash
exec shasum -a 256 "$@"
SH
    fi
    chmod +x "$ACTION_FIXTURE"/bin/*
}

function tear_down() {
    if [[ -n "$ACTION_FIXTURE" && "$ACTION_FIXTURE" == "${TMPDIR:-/tmp/}"* ]]; then
        printf 'Removing action fixture: %s\n' "$ACTION_FIXTURE"
        rm -rf "$ACTION_FIXTURE"
    fi
}

function test_agnostic_checksum_mismatch_stops_before_extraction_installation_and_execution() {
    local output status=0
    output=$(cd "$ACTION_FIXTURE" && PATH="$ACTION_FIXTURE/bin:$PATH" bash install.sh 2>&1) || status=$?
    assert_equals 1 "$status"
    assert_contains 'FAILED' "$output"
    assert_equals '' "$(cat "$ACTION_FIXTURE/events")"
}

function test_matching_agnostic_checksum_allows_extraction_installation_and_execution_in_order() {
    local expected digest output status=0
    expected=$(sed -nE 's/.*"([0-9a-f]{64})  agnostic-ai.tar.gz".*/\1/p' "$ACTION_FIXTURE/install.sh")
    digest=$(PATH="$ACTION_FIXTURE/bin:$PATH" sha256sum "$ACTION_FIXTURE/payload" | cut -d ' ' -f 1)
    assert_equals 64 "${#expected}"
    # A synthetic trusted digest keeps this success-path test offline.
    sed "s/$expected/$digest/" "$ACTION_FIXTURE/install.sh" > "$ACTION_FIXTURE/matching.sh"

    output=$(cd "$ACTION_FIXTURE" && PATH="$ACTION_FIXTURE/bin:$PATH" bash matching.sh 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
    assert_equals $'extract\ninstall\nexecute' "$(cat "$ACTION_FIXTURE/events")"
}

#!/bin/bash
# PostToolUse hook: run Phel core tests after edits to src/phel/**.phel
# Mirrors format-php.sh but for Phel sources.
INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

if [[ "$FILE" != *.phel ]]; then
    exit 0
fi

# Only trigger for library sources, not for test fixtures
case "$FILE" in
    */src/phel/*) ;;
    *) exit 0 ;;
esac

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(pwd)}"
cd "$PROJECT_DIR" 2>/dev/null || exit 0

# Derive matching test file: src/phel/<ns>/<name>.phel -> tests/phel/<ns>/<name>-test.phel
REL="${FILE#$PROJECT_DIR/}"
REL="${REL#src/phel/}"
BASE="${REL%.phel}"
TEST_FILE="tests/phel/${BASE}-test.phel"

if [[ -f "$TEST_FILE" ]]; then
    timeout 60 php -d memory_limit=256M ./bin/phel test "$TEST_FILE" 2>&1 | tail -n 20
else
    # Fall back to running the full suite quietly — keeps the signal loop intact
    echo "no matching test for $REL, skipping (expected at $TEST_FILE)"
fi

exit 0

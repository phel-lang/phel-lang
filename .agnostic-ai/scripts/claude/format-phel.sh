#!/bin/bash
# PostToolUse hook: auto-format Phel files after Edit/Write
INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

if [[ "$FILE" == *.phel ]]; then
    PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(pwd)}"
    cd "$PROJECT_DIR" 2>/dev/null
    ./bin/phel format --quiet "$FILE" 2>/dev/null
fi
exit 0

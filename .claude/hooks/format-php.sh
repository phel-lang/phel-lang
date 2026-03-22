#!/bin/bash
# PostToolUse hook: auto-format PHP files after Edit/Write
INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

if [[ "$FILE" == *.php ]]; then
    cd "$CLAUDE_PROJECT_DIR" 2>/dev/null
    ./vendor/bin/php-cs-fixer fix --quiet "$FILE" 2>/dev/null
fi
exit 0

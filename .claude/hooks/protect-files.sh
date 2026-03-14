#!/bin/bash
# PreToolUse hook: block edits to critical files without explicit confirmation
INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

[[ -z "$FILE" ]] && exit 0

if [[ "$FILE" == */build/release.sh ]] || \
   [[ "$FILE" == */.github/* ]] || \
   [[ "$FILE" == */composer.lock ]]; then
    echo "Protected file: $FILE — edit blocked. Ask user to confirm before retrying." >&2
    exit 2
fi
exit 0

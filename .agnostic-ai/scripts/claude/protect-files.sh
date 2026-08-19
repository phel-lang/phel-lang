#!/bin/bash
# PreToolUse hook: block edits to critical files without explicit confirmation
INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

[[ -z "$FILE" ]] && exit 0

if [[ "$FILE" == */tools/release.sh ]] || \
   [[ "$FILE" == */composer.lock ]] || \
   [[ "$FILE" == */.github/* ]]; then
    echo "Protected file: $FILE. Edit blocked, ask the user to confirm before retrying." >&2
    exit 2
fi
exit 0

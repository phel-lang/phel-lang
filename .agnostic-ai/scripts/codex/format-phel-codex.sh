#!/bin/bash
set -euo pipefail

root="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$root"

if [[ ! -x ./bin/phel ]]; then
  exit 0
fi

input="$(</dev/stdin)"
file_path="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
command="$(printf '%s' "$input" | jq -r '.tool_input.command // empty')"

{
  [[ "$file_path" == *.phel ]] && printf '%s\n' "$file_path"
  printf '%s' "$command" \
    | sed -nE 's/^\*\*\* (Add|Update) File: (.*\.phel)$/\2/p'
} \
  | sort -u \
  | while IFS= read -r file; do
      [[ -f "$file" ]] || continue
      ./bin/phel format --quiet "$file" >/dev/null 2>&1 || true
    done

exit 0

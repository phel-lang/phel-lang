#!/bin/bash
set -euo pipefail

input="$(</dev/stdin)"
tool_name="$(printf '%s' "$input" | jq -r '.tool_name // empty')"
command="$(printf '%s' "$input" | jq -r '.tool_input.command // empty')"
file_path="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"

deny() {
  local reason="$1"
  printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":%s}}\n' "$(jq -Rn --arg s "$reason" '$s')"
}

is_protected_path() {
  local path="$1"
  [[ "$path" == */build/release.sh ]] \
    || [[ "$path" == build/release.sh ]] \
    || [[ "$path" == */composer.lock ]] \
    || [[ "$path" == composer.lock ]] \
    || [[ "$path" == */.github/* ]] \
    || [[ "$path" == .github/* ]]
}

if [[ "$tool_name" == "Bash" ]]; then
  case "$command" in
    *"rm -rf /"*|*"rm -fr /"*|*"sudo "*|*"shutdown"*|*"reboot"*|*"mkfs"*)
      deny "Destructive command blocked by Phel repository policy."
      exit 0
      ;;
  esac
fi

if [[ -n "$file_path" ]] && is_protected_path "$file_path"; then
  deny "Protected file edit blocked. Ask the user to confirm before changing build/release.sh, composer.lock, or .github/*."
  exit 0
fi

if [[ "$tool_name" == "apply_patch" ]]; then
  while IFS= read -r path; do
    if is_protected_path "$path"; then
      deny "Protected file edit blocked. Ask the user to confirm before changing build/release.sh, composer.lock, or .github/*."
      exit 0
    fi
  done < <(printf '%s' "$command" | sed -nE 's/^\*\*\* (Add|Update|Delete) File: (.*)$/\2/p')
fi

exit 0

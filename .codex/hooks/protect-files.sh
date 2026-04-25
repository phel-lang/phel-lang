#!/bin/bash
set -euo pipefail

input="$(cat)"
tool_name="$(printf '%s' "$input" | jq -r '.tool_name // empty')"
command="$(printf '%s' "$input" | jq -r '.tool_input.command // empty')"

deny() {
  local reason="$1"
  printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":%s}}\n' "$(jq -Rn --arg s "$reason" '$s')"
}

if [[ "$tool_name" == "Bash" ]]; then
  case "$command" in
    *"rm -rf /"*|*"rm -fr /"*|*"sudo "*|*"shutdown"*|*"reboot"*|*"mkfs"*)
      deny "Destructive command blocked by Phel repository policy."
      exit 0
      ;;
  esac
fi

if [[ "$tool_name" == "apply_patch" ]]; then
  if printf '%s' "$command" | grep -Eq '(^|\n)(\*\*\* (Update|Delete) File: ).*(build/release\.sh|composer\.lock|\.github/)'; then
    deny "Protected file edit blocked. Ask the user to confirm before changing build/release.sh, composer.lock, or .github/*."
    exit 0
  fi
fi

exit 0

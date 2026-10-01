#!/bin/bash
# Codex PreToolUse hook: blocks destructive shell commands. Claude Code gets the same through permissions.deny.
set -euo pipefail

command="$(jq -r '.tool_input.command // empty')"

case "$command" in
  *"rm -rf /"*|*"rm -fr /"*|*"sudo "*|*"shutdown"*|*"reboot"*|*"mkfs"*)
    printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"Destructive command blocked by Phel repository policy."}}\n'
    ;;
esac
exit 0

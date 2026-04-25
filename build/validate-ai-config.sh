#!/usr/bin/env bash
#
# Validate repository-maintenance AI adapter config.

set -euo pipefail

repo_root=$(cd "$(dirname "$0")/.." && pwd)
cd "$repo_root"

failures=0

fail() {
  echo "FAIL: $*" >&2
  failures=$((failures + 1))
}

ok() {
  echo "OK: $*"
}

require_file() {
  local file=$1
  [[ -f "$file" ]] || fail "Missing $file"
}

require_dir() {
  local dir=$1
  [[ -d "$dir" ]] || fail "Missing $dir"
}

echo "==> Required layout"
require_file AGENTS.md
require_dir .codex
require_dir .claude
require_dir .agents
require_file .codex/config.toml
require_file .codex/hooks.json
require_file .codex/rules/default.rules
require_file .claude/settings.json
require_file .claude/CLAUDE.md

if [[ -e .ai ]]; then
  fail ".ai exists; repo-maintenance adapter files should live in .codex/ or .claude/"
else
  ok "No .ai directory"
fi

if find .codex .claude -type l -print -quit | grep -q .; then
  fail ".codex/.claude should contain concrete adapter files, not symlinks"
else
  ok "No adapter symlinks"
fi

echo "==> JSON"
if jq empty .codex/hooks.json && jq empty .claude/settings.json; then
  ok "Hook/settings JSON parses"
else
  fail "Invalid JSON in .codex/hooks.json or .claude/settings.json"
fi

echo "==> TOML"
if python3 - <<'PY'
from pathlib import Path
import tomllib

paths = [Path(".codex/config.toml"), *sorted(Path(".codex/agents").glob("*.toml"))]
for path in paths:
    with path.open("rb") as handle:
        tomllib.load(handle)
    print(f"OK: {path}")
PY
then
  ok "Codex TOML parses"
else
  fail "Invalid Codex TOML"
fi

echo "==> Codex rules"
if command -v codex >/dev/null 2>&1; then
  composer_decision=$(codex execpolicy check --rules .codex/rules/default.rules -- composer test | jq -r '.decision')
  rm_decision=$(codex execpolicy check --rules .codex/rules/default.rules -- rm -rf / | jq -r '.decision')
  gh_decision=$(codex execpolicy check --rules .codex/rules/default.rules -- gh pr create | jq -r '.decision')

  [[ "$composer_decision" == "allow" ]] || fail "Expected composer test to be allowed, got $composer_decision"
  [[ "$rm_decision" == "forbidden" ]] || fail "Expected rm -rf / to be forbidden, got $rm_decision"
  [[ "$gh_decision" == "prompt" ]] || fail "Expected gh pr create to prompt, got $gh_decision"
  ok "Codex exec-policy samples"
else
  echo "SKIP: codex CLI not installed; skipping exec-policy samples"
fi

echo "==> Codex protected-file hook"
protected_output=$(
  jq -n --arg cmd $'*** Begin Patch\n*** Update File: /tmp/repo/composer.lock\n@@\n-x\n+y\n*** End Patch' \
    '{tool_name:"apply_patch", tool_input:{command:$cmd}}' \
    | .codex/hooks/protect-files.sh
)
protected_decision=$(printf '%s' "$protected_output" | jq -r '.hookSpecificOutput.permissionDecision // empty')
[[ "$protected_decision" == "deny" ]] || fail "Expected protected-file hook to deny composer.lock edit"
ok "Protected-file hook blocks protected edits"

echo "==> Stale references"
if rg -n '\.ai(/|$)|\.agents/project|AI/Claude|\.ai/project' AGENTS.md .agents .claude .codex >/tmp/phel-ai-config-stale.txt; then
  cat /tmp/phel-ai-config-stale.txt >&2
  fail "Found stale adapter-layout references"
else
  ok "No stale adapter-layout references"
fi

echo "==> Downstream skill boundary"
if find .codex -path '*/SKILL.md' -print -quit | grep -q .; then
  fail "Repo-maintenance skills must not live under .codex; use custom agents/hooks or .claude skills"
else
  ok "No Codex SKILL.md files"
fi

if (( failures > 0 )); then
  echo "$failures AI config validation issue(s) found" >&2
  exit 1
fi

echo "AI config validated"

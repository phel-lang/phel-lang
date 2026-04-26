#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: prepare-gh-issue.sh <issue-number|#number|issue-url> [--setup]

Fetch full GitHub issue context, including comments, into:
  .git/codex-gh-issues/issue-<number>.md

Options:
  --setup    also assign the issue author and create a branch from fresh main
USAGE
}

die() {
  printf 'error: %s\n' "$*" >&2
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "missing required command: $1"
}

issue_arg="${1:-}"
setup=false

if [[ -z "$issue_arg" || "$issue_arg" == "-h" || "$issue_arg" == "--help" ]]; then
  usage
  exit 0
fi

shift || true
while [[ $# -gt 0 ]]; do
  case "$1" in
    --setup) setup=true ;;
    *) die "unknown argument: $1" ;;
  esac
  shift
done

require_cmd gh
require_cmd git

issue="$issue_arg"
issue="${issue##*/}"
issue="${issue#\#}"
[[ "$issue" =~ ^[0-9]+$ ]] || die "could not parse issue number from: $issue_arg"

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)" || die "not inside a git repository"
cd "$repo_root"

context_dir="$(git rev-parse --git-path codex-gh-issues)"
mkdir -p "$context_dir"
json_file="$context_dir/issue-$issue.json"
context_file="$context_dir/issue-$issue.md"

gh issue view "$issue" \
  --json number,title,body,author,comments,labels,assignees,state,url \
  >"$json_file"

python3 - "$json_file" "$context_file" <<'PY'
import json
import sys
from pathlib import Path

json_path = Path(sys.argv[1])
context_path = Path(sys.argv[2])
data = json.loads(json_path.read_text())

def login(value):
    if isinstance(value, dict):
        return value.get("login") or value.get("name") or ""
    return ""

labels = ", ".join(label.get("name", "") for label in data.get("labels", []) if label.get("name")) or "(none)"
assignees = ", ".join(login(user) for user in data.get("assignees", []) if login(user)) or "(none)"
author = login(data.get("author", {})) or "(unknown)"

lines = [
    f"# Issue #{data.get('number')}: {data.get('title', '')}",
    "",
    f"- URL: {data.get('url', '')}",
    f"- State: {data.get('state', '')}",
    f"- Author: {author}",
    f"- Labels: {labels}",
    f"- Assignees: {assignees}",
    "",
    "## Body",
    "",
    data.get("body") or "(empty)",
    "",
    "## Comments",
]

comments = data.get("comments") or []
if not comments:
    lines.extend(["", "(none)"])
else:
    for idx, comment in enumerate(comments, 1):
        commenter = login(comment.get("author", {})) or "(unknown)"
        created = comment.get("createdAt", "")
        lines.extend([
            "",
            f"### Comment {idx} by {commenter} at {created}",
            "",
            comment.get("body") or "(empty)",
        ])

context_path.write_text("\n".join(lines).rstrip() + "\n")
print(context_path)
PY

title="$(gh issue view "$issue" --json title --jq .title)"
author="$(gh issue view "$issue" --json author --jq .author.login)"
state="$(gh issue view "$issue" --json state --jq .state)"
labels="$(gh issue view "$issue" --json labels --jq '[.labels[].name] | join(" ")')"

prefix="feat"
case " $labels " in
  *" bug "*) prefix="fix" ;;
  *" enhancement "*|*" feature "*) prefix="feat" ;;
  *" documentation "*|*" docs "*) prefix="docs" ;;
  *" performance "*|*" perf "*) prefix="perf" ;;
  *" refactor "*) prefix="ref" ;;
  *" test "*|*" tests "*) prefix="test" ;;
esac

slug="$(printf '%s' "$title" \
  | tr '[:upper:]' '[:lower:]' \
  | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//; s/-+/-/g' \
  | cut -c 1-60 \
  | sed -E 's/-+$//')"
[[ -n "$slug" ]] || slug="issue"
branch="$prefix/$issue-$slug"

printf 'Issue context: %s\n' "$context_file"
printf 'Issue state: %s\n' "$state"
printf 'Suggested branch: %s\n' "$branch"

if [[ "$setup" != true ]]; then
  exit 0
fi

if [[ -n "$(git status --porcelain)" ]]; then
  die "worktree is dirty; inspect changes before creating a fresh issue branch"
fi

if [[ "$state" != "OPEN" ]]; then
  die "issue is not open: $state"
fi

if [[ -n "$author" && "$author" != "null" ]]; then
  if ! gh issue edit "$issue" --add-assignee "$author" >/dev/null; then
    printf 'warning: could not assign issue author "%s"; continuing without changing assignee\n' "$author" >&2
  fi
fi

git switch main
git pull --ff-only --prune
git switch -c "$branch"
printf 'Created branch: %s\n' "$branch"

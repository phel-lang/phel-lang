#!/bin/sh
# PostToolUse hook for Claude Code and Codex: formats the PHP and Phel files an edit left on disk.
files=$(agnostic-ai hook paths 2>/dev/null) || exit 0
root=$(git rev-parse --show-toplevel 2>/dev/null) || exit 0
here=$(pwd)
cd "$root" || exit 0
printf '%s\n' "$files" | while IFS= read -r f; do
  [ -n "$f" ] || continue
  case "$f" in /*) ;; *) f="$here/$f" ;; esac
  [ -f "$f" ] || continue
  case "$f" in
    *.php) [ -x ./vendor/bin/php-cs-fixer ] && ./vendor/bin/php-cs-fixer fix --quiet "$f" >/dev/null 2>&1 ;;
    *.phel) [ -x ./bin/phel ] && ./bin/phel format --quiet "$f" >/dev/null 2>&1 ;;
  esac
done
exit 0

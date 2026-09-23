#!/bin/bash
# SessionStart hook: re-inject key context after compaction
cat <<'EOF'
## Context Reminder (post-compaction)

**Phel** is a Lisp that compiles to PHP (Clojure-inspired).

- Conventional commits (`feat:`, `fix:`, `ref:`, `perf:`, `chore:`, `docs:`, `test:`). NEVER mention AI tooling.
- Test: focused first (`./vendor/bin/phpunit --filter=X`, `./bin/phel test <file>`), then `COMPOSER_PROCESS_TIMEOUT=0 composer test` once.
- Auto-fix: `composer fix` (rector + cs-fixer). PHP edits auto-format via PostToolUse hook.
- Compiler: Lexer → Parser → Reader → Analyzer → Simplifier → Emitter (never skip phases)
- Each `src/php/<Module>/CLAUDE.md` documents the module — read before modifying.
- Protected files: `tools/release.sh`, `.github/*`, `composer.lock`
- PRs: follow `.github/PULL_REQUEST_TEMPLATE.md` exactly (with emoji prefixes).
- Agent config: edit `.agnostic-ai/`, then `agnostic-ai sync`. Never edit `.claude/`, `.codex/`, `AGENTS.md`.
- `feat:`/`fix:` commits must update `CHANGELOG.md` under `## Unreleased`.
EOF

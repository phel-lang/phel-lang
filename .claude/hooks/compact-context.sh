#!/bin/bash
# SessionStart hook: re-inject key context after compaction
cat <<'EOF'
## Context Reminder (post-compaction)

**Phel** is a Lisp that compiles to PHP (Clojure/Janet-inspired).

- Conventional commits (`feat:`, `fix:`, `ref:`, `chore:`). NEVER mention AI/Claude.
- Test: `composer test` (all), `test-compiler` (PHP), `test-core` (Phel)
- Auto-fix: `composer fix` (rector + cs-fixer). PHP edits auto-format via PostToolUse hook.
- Compiler: Lexer → Parser → Analyzer → Emitter (never skip phases)
- Each `src/php/<Module>/CLAUDE.md` documents the module — read before modifying.
- Protected files: `build/release.sh`, `.github/*`, `composer.lock`
- PRs: follow `.github/PULL_REQUEST_TEMPLATE.md` exactly (with emoji prefixes).
- `feat:`/`fix:` commits must update `CHANGELOG.md` under `## Unreleased`.
EOF

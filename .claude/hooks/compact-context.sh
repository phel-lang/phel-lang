#!/bin/bash
# SessionStart hook: re-inject key context after compaction
cat <<'EOF'
## Context Reminder (post-compaction)

**Phel** is a Lisp that compiles to PHP (Clojure/Janet-inspired).

- Conventional commits only (`feat:`, `fix:`, `ref:`, `chore:`). NEVER mention AI/Claude.
- Test: `composer test` (all), `composer test-compiler` (PHP), `composer test-core` (Phel)
- Auto-fix: `composer fix` (rector + cs-fixer)
- Module boundaries: access only via Facades (Gacela pattern)
- Compiler: Lexer → Parser → Analyzer → Emitter (never skip phases)
- `Lang/` has zero dependencies. `Shared/` stays thin.
- PHP edits are auto-formatted by a PostToolUse hook.
- `build/release.sh`, `.github/*`, `composer.lock` are protected files.
EOF

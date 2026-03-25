#!/bin/bash
# SessionStart hook: re-inject key context after compaction
cat <<'EOF'
## Context Reminder (post-compaction)

**Phel** is a Lisp that compiles to PHP (Clojure/Janet-inspired).

- Conventional commits (`feat:`, `fix:`, `ref:`, `chore:`, `perf:`). NEVER mention AI/Claude.
- Test: `composer test` (all), `composer test-compiler` (PHP), `composer test-core` (Phel)
- Benchmark: `composer phpbench` (run), `composer phpbench-base` (baseline), `composer phpbench-ref` (compare)
- Auto-fix: `composer fix` (rector + cs-fixer)
- Module boundaries: access only via Facades (Gacela pattern)
- Compiler: Lexer → Parser → Analyzer → Emitter (never skip phases)
- `Lang/` has zero dependencies. `Shared/` stays thin.
- PHP edits auto-format via PostToolUse hook (no manual cs-fixer needed).
- `build/release.sh`, `.github/*`, `composer.lock` are protected files.
- PRs: follow `.github/PULL_REQUEST_TEMPLATE.md` exactly (including emoji prefixes).
EOF

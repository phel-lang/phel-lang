#!/bin/bash
set -euo pipefail

cat <<'EOF'
## Phel Repository Context

- Read AGENTS.md for repository policy before editing.
- Read src/php/<Module>/CLAUDE.md before modifying a PHP module.
- Keep .codex/ for Codex-native config, .claude/ for Claude Code, and .agents/ for downstream Phel users.
- Use composer test-compiler for PHP compiler/runtime changes and composer test-core for src/phel changes.
EOF

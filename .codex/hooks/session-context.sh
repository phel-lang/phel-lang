#!/bin/bash
set -euo pipefail

cat <<'EOF'
## Phel Repository Context

- Read AGENTS.md for repository policy before editing.
- Read src/php/<Module>/CLAUDE.md before modifying a PHP module.
- Keep .codex/ for Codex-native config, .claude/ for Claude Code, .agents/ for repo-local assets, and resources/agents/ for downstream Phel user assets.
- Use composer test-compiler for PHP compiler/runtime changes and composer test-core for src/phel changes.
EOF

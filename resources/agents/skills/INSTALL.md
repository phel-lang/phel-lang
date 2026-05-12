# Install agent skills

```bash
./vendor/bin/phel agent-install <platform>           # single platform
./vendor/bin/phel agent-install --all                # every platform
./vendor/bin/phel agent-install --all --with-docs    # also install docs into .agents/
./vendor/bin/phel agent-install --dry-run claude     # preview
./vendor/bin/phel agent-install --force claude       # overwrite without backup
```

Existing targets back up to `<path>.pre-phel.bak`. `--force` skips backup.

Each installed file gets a footer `<!-- phel-agents vX.Y.Z -->` stamped from `resources/agents/VERSION`. Re-running `agent-install` after `composer update phel-lang/phel-lang` refreshes the stamp.

## Destinations

| Platform | Source template | Installed path |
|----------|------------------|-----------------|
| Claude Code | `skills/claude/phel-lang/SKILL.md` | `.claude/skills/phel-lang/SKILL.md` |
| Cursor | `skills/cursor/phel.mdc` | `.cursor/rules/phel.mdc` |
| Codex (or AGENTS.md) | `skills/codex/AGENTS.md` | `AGENTS.md` |
| Gemini CLI | `skills/gemini/GEMINI.md` | `GEMINI.md` |
| GitHub Copilot | `skills/copilot/copilot-instructions.md` | `.github/copilot-instructions.md` |
| Aider | `skills/aider/CONVENTIONS.md` | `CONVENTIONS.md` |

## Recommended setup

1. `./vendor/bin/phel agent-install --all --with-docs` — installs every skill plus the `.agents/` reference tree.
2. Commit `.agents/` and the per-platform files so teammates' agents share the same context.
3. After upgrading phel-lang, re-run `agent-install --all --force` to refresh content and version stamp.

## Manual fallback

Copy source → installed path. Adapters fall back to `vendor/phel-lang/phel-lang/resources/agents/` when the user project lacks its own `.agents/` tree.

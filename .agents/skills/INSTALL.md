# Install agent skills

```bash
./vendor/bin/phel agent-install <platform>           # single platform
./vendor/bin/phel agent-install --all                # every platform
./vendor/bin/phel agent-install --all --with-docs    # also copy .agents/ tree
./vendor/bin/phel agent-install --dry-run claude     # preview
```

Existing targets back up to `<path>.pre-phel.bak`. `--force` skips backup.

## Destinations

| Platform | Source template | Installed path |
|----------|------------------|-----------------|
| Claude Code | `skills/claude/phel-lang/SKILL.md` | `.claude/skills/phel-lang/SKILL.md` |
| Cursor | `skills/cursor/phel.mdc` | `.cursor/rules/phel.mdc` |
| Codex (or AGENTS.md) | `skills/codex/AGENTS.md` | `AGENTS.md` |
| Gemini CLI | `skills/gemini/GEMINI.md` | `GEMINI.md` |
| GitHub Copilot | `skills/copilot/copilot-instructions.md` | `.github/copilot-instructions.md` |
| Aider | `skills/aider/CONVENTIONS.md` | `CONVENTIONS.md` |

## Manual fallback

Copy source → installed path. Adapters fall back to `vendor/phel-lang/phel-lang/.agents/` when the user project lacks its own `.agents/` tree.

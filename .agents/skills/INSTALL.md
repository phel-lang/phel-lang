# Install agent skills

Use the CLI:

```bash
./vendor/bin/phel agent-install <platform>    # single platform
./vendor/bin/phel agent-install --all         # every platform
./vendor/bin/phel agent-install --all --with-docs   # also copy .agents/ tree
./vendor/bin/phel agent-install --dry-run claude    # preview
```

Backs up any existing target to `<path>.pre-phel.bak`. Pass `--force` to skip backup.

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

Copy the source file to the installed path. Mirror `.agents/` into the user project if you want full task recipes and examples available locally; otherwise adapters fall back to the package bundled under `vendor/phel-lang/phel-lang/.agents/`.

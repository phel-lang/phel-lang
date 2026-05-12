# Install agent skills

```bash
./vendor/bin/phel agent-install <platform>           # single platform
./vendor/bin/phel agent-install --all                # every platform
./vendor/bin/phel agent-install --auto               # only platforms already used in the project
./vendor/bin/phel agent-install --all --with-docs    # also install docs into .agents/
./vendor/bin/phel agent-install --dry-run claude     # preview
./vendor/bin/phel agent-install --force claude       # overwrite without backup
./vendor/bin/phel agent-install --check              # report install + version drift; exit 1 if stale
./vendor/bin/phel agent-install --list               # list platforms with sources, targets, and state
./vendor/bin/phel agent-install --uninstall claude   # remove (restores .pre-phel.bak if present)
./vendor/bin/phel agent-install --uninstall --all --with-docs   # full removal
```

Existing targets back up to `<path>.pre-phel.bak`. `--force` skips backup.

Each installed file gets a footer `<!-- phel-agents vX.Y.Z -->` stamped from `resources/agents/VERSION`. Re-running `agent-install` after `composer update phel-lang/phel-lang` refreshes the stamp.

`phel doctor` also reports installed agent skills and flags stale versions in one place — no need to remember `--check`.

## Destinations

| Platform | Source template | Installed path | Detection signal |
|----------|------------------|-----------------|------------------|
| Claude Code | `skills/claude/phel-lang/SKILL.md` | `.claude/skills/phel-lang/SKILL.md` | `.claude/` |
| Cursor | `skills/cursor/phel.mdc` | `.cursor/rules/phel.mdc` | `.cursor/` |
| Codex (or AGENTS.md) | `skills/codex/AGENTS.md` | `AGENTS.md` | `AGENTS.md`, `.codex/` |
| Gemini CLI | `skills/gemini/GEMINI.md` | `GEMINI.md` | `GEMINI.md`, `.gemini/` |
| GitHub Copilot | `skills/copilot/copilot-instructions.md` | `.github/copilot-instructions.md` | `.github/copilot-instructions.md` |
| Aider | `skills/aider/CONVENTIONS.md` | `CONVENTIONS.md` | `CONVENTIONS.md`, `.aider.conf.yml` |

`--auto` uses the detection signals to install only for the agents you already use.

## Recommended setup

1. `./vendor/bin/phel agent-install --auto --with-docs` — installs skills for agents the project already touches, plus `.agents/` reference tree.
2. Commit `.agents/` and the per-platform files so teammates' agents share the same context.
3. After upgrading phel-lang, run `agent-install --check`; if it exits 1, re-run `agent-install --all --force` to refresh content and version stamp.

## Manual fallback

Copy source → installed path. Adapters fall back to `vendor/phel-lang/phel-lang/resources/agents/` when the user project lacks its own `.agents/` tree.

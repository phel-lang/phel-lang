# tools/git-hooks/

Git hooks shipped with the phel-lang repo. Opt-in: nothing runs until you
install them into your local `.git/hooks/` via `init.sh`.

## Install

From the repo root:

```bash
./tools/git-hooks/init.sh
```

This symlinks `pre-commit` and copies the other hooks into `.git/hooks/`.
The post-* hooks run on checkout and pull, so they are copies: a branch you
check out cannot change what they run. Re-run `init.sh` after a change to this
directory. `init.sh` replaces only hooks it installed itself; a hook from another
tool (Git LFS, a hook manager) is left alone with a warning, to merge by hand.

## Hooks

| File | Hook | What it does |
|---|---|---|
| [`pre-commit.sh`](pre-commit.sh) | `pre-commit` | Runs `composer test-all` when the staged diff includes `.php` or `.phel` files. Skips when only docs/config changed — so commits to `.md`, `composer.json`, CI files, etc. stay fast. |
| [`post-merge.sh`](post-merge.sh) | `post-merge` | Runs `agnostic-ai sync` when a pull changed `.agnostic-ai/` or `agnostic-ai.yaml`, so the gitignored `.claude/`, `.codex/` and `AGENTS.md` never lag behind the specs. |
| [`post-checkout.sh`](post-checkout.sh) | `post-checkout` | The same on a branch switch. |
| [`post-rewrite.sh`](post-rewrite.sh) | `post-rewrite` | The same after `git pull --rebase` or `git rebase`, which never run `post-merge`. |
| [`sync-agent-config.sh`](sync-agent-config.sh) | (helper) | Shared by the three hooks above. Syncs only when the specs match `origin/main` and have no local changes, since sync emits hooks the next agent session runs; on any other branch it prints a notice instead. Never fails the git command, and does nothing without `agnostic-ai`. |
| [`init.sh`](init.sh) | — | Installer. Symlinks `pre-commit`, copies the rest into `.git/hooks/`. |

## Skipping a hook

For an emergency commit only — never as a habit:

```bash
git commit --no-verify -m "..."
```

If a hook fails, fix the underlying issue rather than bypassing the check.

## Adding a new hook

1. Drop the script in this directory as `<hook-name>.sh` (e.g.
   `commit-msg.sh`, `pre-push.sh`).
2. `chmod +x` it.
3. Update [`init.sh`](init.sh) to symlink it next to the existing one.
4. Document it in the table above.

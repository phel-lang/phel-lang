# tools/git-hooks/

Git hooks shipped with the phel-lang repo. Opt-in: nothing runs until you
install them into your local `.git/hooks/` via `init.sh`.

## Install

From the repo root:

```bash
./tools/git-hooks/init.sh
```

This symlinks the hooks below into `.git/hooks/`. Re-run after pulling a
change to this directory if a new hook is added.

## Hooks

| File | Hook | What it does |
|---|---|---|
| [`pre-commit.sh`](pre-commit.sh) | `pre-commit` | Runs `composer test-all` when the staged diff includes `.php` or `.phel` files. Skips when only docs/config changed — so commits to `.md`, `composer.json`, CI files, etc. stay fast. |
| [`init.sh`](init.sh) | — | Installer. Symlinks every hook above into `.git/hooks/`. |

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

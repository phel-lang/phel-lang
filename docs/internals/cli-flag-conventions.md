# CLI Flag Conventions

Conventions for option names and short aliases across Phel's CLI commands, so
muscle memory transfers between commands. New commands MUST follow these.

## Standard options

| Concept | Long | Short | Notes |
|---|---|---|---|
| Output format | `--format` | `-f` | Value flag (`table`/`json`/...). `-f` is also `--filter` on `phel test` (pre-existing); a command never has both, so there is no in-command clash. |
| Output destination (file) | `--output` | `-o` | Write the report/result to a file instead of stdout. |
| Sort order | `--sort` | `-s` | Value flag (e.g. `phel profile --sort`). |
| Disable caching | `--no-cache` | — | Boolean. Paired with `--cache` where a default-on cache exists (`build`). |
| Config file path | `--config` | — | Path to a command-specific config. |
| Preview without writing | `--dry-run` | — | Boolean; print intended actions only. |
| Overwrite existing files | `--force` | — | Boolean. |
| Optimization level | `--optimization-level` | `-O` | `build`/`compile`. |

Global flags (`--help/-h`, `--quiet/-q`, `--verbose/-v`, `--version/-V`,
`--no-interaction/-n`, `--ansi/--no-ansi`) come from Symfony Console; never
re-declare them or reuse their short letters.

## Reserved short letters

`-h -q -v -V -n` are Symfony globals. Within Phel commands: `-f` = format
(or filter on `test`), `-o` = output, `-s` = sort, `-O` = optimization level,
`-t` is command-local (`compile --target`, `init --template`, `run --with-time`),
`-p` = port (`nrepl`), `-m` = minimal (`init`), `-b` = backend (`watch`).

## Known drift (deferred — needs a deprecation cycle)

These would be breaking renames, so they are intentionally left for a separate
change that keeps the old name as a deprecated alias first:

- `phel index --out` should become `--output` (`-o`) to match `profile`/`test`.
- `phel test --reporter` overlaps conceptually with `--format`; it stays a
  distinct, repeatable flag for now (it selects reporters, not a single format).
- `phel config --json` is a boolean shorthand for "format = json"; left as-is.

When adding such a rename, register the new name + short alias, keep the old
option accepted (mark it deprecated in the description), and read whichever is
provided.

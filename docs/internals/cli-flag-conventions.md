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

## Command aliases

High-frequency commands have short aliases (Symfony `setAliases`): `run`→`r`,
`test`→`t`, `build`→`b`, `eval`→`e`, `format`→`fmt`. Keep aliases unique across
the whole command surface; an ambiguous alias makes `find()` throw.

## Renamed options (deprecated aliases kept)

These were aligned to the conventions above; the old names still work but are
marked `[deprecated]` and print a one-line notice (via
`Phel\Shared\Console\DeprecatedOptionWarner`, written to stderr so it never
corrupts machine-readable stdout):

- `phel index --output` (`-o`) is canonical; `--out` is the deprecated alias.
- `phel config --format=json` is canonical; `--json` is the deprecated alias.
- `phel test --reporter` stays a distinct, repeatable flag (it selects
  reporters, not a single output format) — intentionally **not** renamed.

When renaming an option: register the new name + short alias, keep the old
option accepted (mark it `[deprecated]` in the description), warn via
`DeprecatedOptionWarner`, and read whichever is provided (new wins).

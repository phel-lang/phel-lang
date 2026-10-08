# CLI Flag Conventions

Conventions for option names and short aliases across Phel's CLI commands, so
muscle memory transfers between commands. New commands MUST follow these.

## Standard options

| Concept | Long | Short | Notes |
|---|---|---|---|
| Output format | `--format` | `-f` | Value flag (`text`/`json`/...); `text` is the default. `-f` is also `--filter` on `phel test` (pre-existing); a command never has both, so there is no in-command clash. |
| Output destination (file) | `--output` | `-o` | Write the report/result to a file instead of stdout. |
| Sort order | `--sort` | `-s` | Value flag (e.g. `phel profile --sort`). |
| Disable caching | `--no-cache` | — | Boolean. Paired with `--cache` where a default-on cache exists (`build`). |
| Config file path | `--config` | — | Path to a command-specific config. |
| Preview without writing | `--dry-run` | — | Boolean; print intended actions only. |
| Overwrite existing files | `--force` | — | Boolean. |
| Optimization level | `--optimization-level` | `-O` | `build` only; every other command reads the configured level, which `PHEL_OPTIMIZATION_LEVEL` overrides ([CLI reference](../cli-reference.md#optimization-level)). |
| Expand a collapsed trace | `--stack-trace` | — | Boolean. Declared once in `StackTraceOption`; `run`, `eval`, `repl` and `test` all reuse its name and description. |

Global flags (`--help/-h`, `--quiet/-q`, `--verbose/-v`, `--version/-V`,
`--no-interaction/-n`, `--ansi/--no-ansi`) come from Symfony Console; never
re-declare them or reuse their short letters.

## Reserved short letters

`-h -q -v -V -n` are Symfony globals. Within Phel commands: `-f` = format
(or filter on `test`), `-o` = output, `-s` = sort (`--simple` on `ns`, pre-existing),
`-O` = optimization level,
`-t` is command-local (`compile --target`, `init --template`, `run --with-time`),
`-p` = port (`nrepl`), `-m` = minimal (`init`), `-b` = backend (`watch`).

## Command aliases

High-frequency commands have short aliases (Symfony `setAliases`): `run`→`r`,
`test`→`t`, `build`→`b`, `eval`→`e`, `format`→`fmt`. Keep aliases unique across
the whole command surface; an ambiguous alias makes `find()` throw.

## Renaming an option

`phel index --output` (`-o`) and `phel config --format=json` were both renamed
to fit the conventions above. Each kept its old spelling as a deprecated alias
for a release cycle; both aliases are now removed (see
[../migration/removed-deprecated-core-fns.md](../migration/removed-deprecated-core-fns.md)).

`phel mutate --reporter` is renamed to `--format` (`-f`) and kept as a
deprecated alias (#3525). The default format names `human` (`lint`) and `table`
(`doc`, `profile`) became `text` and stay accepted as values.

`phel test --reporter` stays a distinct, repeatable flag (it selects reporters,
not a single output format) and is intentionally **not** renamed.

When renaming an option: register the new name plus its short alias, keep the
old option accepted for at least one release (mark it `[deprecated]` in the
description) and read whichever is provided; both with different values is an
invocation error (exit 2). The deprecation notice belongs on stderr so it never
corrupts machine-readable stdout. `mutate --reporter` is the only rename in
flight, so the notice lives in `MutateCommand` rather than a shared helper.

# ADR 0022: The CLI's machine surface is under semver, its human text is not

- **Status**: Accepted
- **Date**: 2026-10-08

## Context

The VS Code extension, the IntelliJ plugin, `setup-phel-action` and CI scripts
drive Phel through `phel`, not through PHP. They read exit codes, JSON fields,
`--format=github` annotations, `api-daemon` replies, two nREPL ops, environment
variables and `.nrepl-port`. Until now the stability policy named one of those
surfaces: the `code` field of `phel lint --format=json`.

Issue [#3525](https://github.com/phel-lang/phel-lang/issues/3525) listed what
the other surfaces did, and they disagreed across commands. A missing path
exited 0, 1 or 2 depending on the command. Columns were 0-based in diagnostics
and 1-based in the index. `uri` was absolute in `lint` and as typed in
`analyze`. JSON on stdout came after progress lines or the program's own
output. The default format was `human`, `text` or `table`. `PHEL_WARN_DEPRECATIONS=false`
turned notices on. `config --format=json` ignored the environment. The LSP
reported version `0.1.0`.

Writing the boundary down before `1.0` freezes whatever it describes. So the
inconsistencies were fixed first, in #3584, #3609, #3615, #3625, #3628, #3631,
#3632 and #3633, and this record describes behaviour that already holds.

## Decision

The parts of the CLI a program reads are covered by semver. The parts a person
reads are not.

**Covered**, changed only in a major:

- Command names and their aliases, and the options and values each command
  documents in `--help` and in `docs/cli-reference.md`.
- Exit codes:
  - `0`: the command ran and found nothing to fail on.
  - `1`: the command ran and found something: an error diagnostic, a failing
    test, a file to reformat, a score below its floor.
  - `2`: the command could not run as asked: a missing path, an unknown option
    value, a missing `--config` or `--ref` file, a bad environment variable. The
    problem goes to stderr and stdout stays empty. One missing path among
    several fails the run; it is never dropped.
- Positions. Every line and column a command prints is 1-based. The LSP sends
  the 0-based positions its protocol defines and converts at that edge.
- Paths. A JSON field naming a source file (`uri`, `file`) holds an absolute
  path. The `api-daemon` returns the `uri` the request sent. `--format=github`
  prints `file=` relative to the working directory, as GitHub expects.
- Machine modes. When a command prints JSON, TAP, JUnit XML or GitHub
  annotations on stdout, stdout carries that output and nothing else. Progress,
  timings, notices and what the loaded program prints go to stderr.
- `--format` (`-f`) picks a single output format on every command that has more
  than one, and `text` is the default name. `mutate --reporter` stays as a
  deprecated alias. `test --reporter` is a different flag: it selects one or
  more test reporters.
- Environment variables. One parser reads every switch: `1`, `true`, `yes`,
  `on` and `0`, `false`, `no`, `off`, in any case. A value it does not know, or
  a number that is not one, exits 2 naming the variable. `CI` is lenient and
  `NO_COLOR` follows no-color.org, because other tools set them.
- `config --format=json` reports the values a command in the same shell runs
  with, after the environment, and every key `PhelConfig` defines.
- Machine-readable field names and their meaning. Fields are only added, never
  renamed, removed or given a new meaning.
- The `api-daemon` methods, including `version`, which returns the running Phel
  version. The LSP reports the same version in `serverInfo.version`.
- Phel's own nREPL ops, `reload` and `run-tests`, and the `.nrepl-port` file.

**Not covered**, free to change in any release:

- Human-facing text: messages, tables, colours, the `text` format of every
  command, progress lines, help wording.
- Command prefixes. Symfony runs `phel li` as `phel lint` while the prefix is
  unique; a new command can make it ambiguous.
- Symfony's built-in commands (`help`, `list`, `completion`) and global options,
  and the Gacela commands (`cache:warm`, `debug:*`, `list:modules`,
  `profile:report`, `validate:config`). They follow those projects' versions.
- Hidden worker commands (`_test-worker`, `_mutate-worker`).
- `profile --format=json`, whose names come from the profiler's internals.
- The file `bench --store` writes and `bench --ref` reads.
- The OPcache switches `PHEL_OPCACHE_REEXEC` and `PHEL_NO_OPCACHE_REEXEC`, a
  tuning knob.
- What a program run by `run`, `eval`, `test` or `repl` prints, and the exit
  code it chooses.

## Consequences

Editor plugins and CI scripts can take `1.x` updates without reading a diff,
the same promise ADR 0021 gives PHP hosts. A tool that matches on human text
gets no warning when it changes, and the policy says so.

Every new command, option, field or method joins the covered set the day it
ships. A wrong first spelling is a major to undo, so a new machine output is
reviewed the way a new public PHP signature is.

Adding a field is allowed, so a consumer must ignore fields it does not know.
A strict schema validator on Phel's JSON breaks on a minor; that is the
consumer's choice, not a Phel break.

What fails when it is broken: an editor plugin that reads a column one off
underlines the wrong character; a CI step that greps exit codes passes a run
that did not happen; a script that pipes JSON into `jq` fails on the first
progress line. Each of these happened before the slices above.

## Enforcement

- `InvocationErrorExitCodeTest`: exit 2 with the problem on stderr only.
- `MachineModeStdoutTest`: stdout is one JSON or XML document, or a TAP stream.
- `EnvVarParsingTest` and `EnvVarTest`: the shared parser and exit 2.
- `ConfigJsonEnvOverridesTest`: environment overrides and every config key.
- `DiagnosticSchemaTest`: `analyze` and `lint` report one error with the same fields.
- `JsonRpcDispatcherVersionTest` and `LspConfigTest`: the running version.
- `CliFlagConventionsTest` and `CommandAliasesTest`: short options and aliases.
- `CliReferenceCoverageTest`: the command table and the `api-daemon` method
  table in `docs/cli-reference.md` match the console and the dispatcher.

No test compares field names against the last release. The review of a pull
request that touches a machine output is the gate.

## Alternatives considered

- **Cover only the `code` field, as before.** Leaves every other surface to be
  argued per issue after `1.0`.
- **Cover everything, human text included.** Freezes wording that has no
  program reading it, and turns every message fix into a major.
- **Publish JSON schemas and version them.** More to maintain than the field
  lists, and no consumer asked for one.
- **Freeze the surfaces as they were.** Freezes the inconsistencies too, which
  cost a major each to fix later.

## See also

[Stability policy](../stability.md#command-line-interface) ·
[CLI reference](../cli-reference.md) ·
[CLI flag conventions](../internals/cli-flag-conventions.md) ·
[ADR 0006](0006-one-opt-in-deprecation-channel.md) ·
[ADR 0021](0021-public-embedding-types-and-internal-tooling.md)

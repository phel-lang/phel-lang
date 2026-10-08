# CLI Reference & DX Guide

Every `phel` command, the common workflows, and the dev loop. `phel <command>
--help` gives full options and a usage example; `phel completion bash|zsh|fish`
enables tab-completion (setup in the [README](../README.md)).

> Tutorials live on [phel-lang.org](https://phel-lang.org/documentation/tooling/cli-commands/);
> this is the quick reference kept next to the code.

## Commands

| Command | Purpose |
|---|---|
| `agent-install` | Install agent skill files (Claude, Cursor, Codex, Gemini, Copilot, Aider) into the current project |
| `analyze` | Run semantic analysis on Phel source files or directories and emit JSON diagnostics |
| `api-daemon` | Long-running JSON-RPC daemon exposing the Api semantic-analysis facade over stdio (for tooling) |
| `balance` | Report unbalanced `()`, `[]`, `{}` in Phel files; append the missing closers with `--fix` |
| `bench` | Run the `defbench` benchmarks (all of them, or the files/namespaces you pass) |
| `build` `b` | Build the current project: compile every namespace to PHP in the output dir |
| `cache:clear` | Clear the temp and cache directories, and empty the OPcache file cache |
| `compile` | Compile a Phel snippet/file/stdin and print the emitted PHP — does not evaluate |
| `config` | Show the effective Phel configuration and where it comes from |
| `doc` | Display the docs for any/all Phel functions |
| `doctor` | Check system requirements (PHP, extensions, OPcache cold-start, cache size) for the Phel CLI |
| `eval` `e` | Evaluate a Phel expression (or stdin) and print the result |
| `explain` | Explain a Phel error code or lint rule: what it means, a minimal example, the fix. No argument lists every code. `--format=json` for tools |
| `export` | Export all definitions tagged `{:export true}` as PHP classes |
| `format` `fmt` | Format the given files (defaults to the configured format dirs) |
| `index` | Build a project-level symbol index across source directories |
| `init` | Initialize a new Phel project (config, main namespace, test, .gitignore) |
| `lint` | Run the semantic linter on Phel files or directories (no rewrite) |
| `lsp` | Start the Phel Language Server (LSP v3.17 over stdio) |
| `mutate` | Mutation testing: mutate every `defn` under the given paths and list the mutants the test suite does not catch (`--min-msi` gates) |
| `nrepl` | Start an nREPL server for editor tooling (bencode over TCP) |
| `ns` `loaded-ns` | List all loaded namespaces, or inspect one |
| `profile` | Profile a script: per-fn call counts/timings + compile-phase costs |
| `repl` | Start an interactive REPL |
| `run` `r` | Run a Phel file or namespace (auto-detects the entry point) |
| `test` `t` | Run the test suite (all tests, or the files/namespaces you pass) |
| `watch` | Watch Phel files and reload changed namespaces on change |

Symfony adds `help`, `list` and `completion`. Gacela registers a few more
through `Console/Infrastructure/Command/FrameworkCommands`: `cache:warm`,
`debug:container`, `debug:dependencies`, `debug:modules`, `list:modules`,
`profile:report` and `validate:config`. They inspect the module wiring rather than
your Phel code, and `phel list` shows them alongside the commands above. The
table above is the command surface the
[stability policy](stability.md#command-line-interface) covers; these are not
part of it.

`phel api-daemon` reads one JSON request per line on stdin and writes one JSON
response per line. Its methods are listed under
[Output shapes](#api-daemon). `version` takes no params and returns the running
Phel version, so an editor can check it is compatible:

```sh
echo '{"id":1,"method":"version"}' | phel api-daemon
# {"id":1,"result":"v0.54.0"}
```

`phel config` prints the values a command of the same shell runs with:
`phel-config.php` and `phel-config-local.php`, then `PHEL_CACHE_DIR`,
`PHEL_DIR`, `PHEL_OPTIMIZATION_LEVEL`, `PHEL_WARN_DEPRECATIONS` and the
`--warn-deprecations` flag on top. `phel config --format=json` prints one
object with every key `PhelConfig` defines, in the same order, so
`PHEL_OPTIMIZATION_LEVEL=2 phel config --format=json` reports
`"optimization-level": 2`. Keys are only added, never renamed or removed. The
validation in the text output checks the config files, not the env vars.

Diagnostics from `lint`, `analyze` and the `api-daemon` `analyzeSource` method
use 1-based lines and columns, and `endCol` is one past the last character.
`lint` and `analyze` print `uri` as an absolute path; `api-daemon` returns the
`uri` the request sent. `lint --format=github` prints `file=` relative to the
working directory, the form GitHub annotations expect. `phel lsp` sends the
0-based positions the protocol defines.

In a machine mode stdout carries only the machine output, so a tool can parse
all of it: `analyze`, `api-daemon`, `index`, `lint --format=json|github`,
`config --format=json`, `explain --format=json`, `doc --format=json`,
`profile --format=json`, `mutate --reporter=json`, and `test --reporter=tap` or
`--reporter=junit-xml` without `-o`. Progress, timings, notices and what the
loaded code prints go to stderr. `index` prints its JSON summary on stdout with
or without `-o`.

## Editor setup

`phel lsp` (Language Server, stdio) and `phel nrepl` (default `127.0.0.1:7888`) plug
Phel into your editor. Per-editor setup lives on the website:

- **LSP** — VS Code, Emacs (eglot / lsp-mode), Vim / Neovim (coc.nvim / vim-lsp): [Editor support](https://phel-lang.org/documentation/tooling/editor-support/)
- **nREPL** — Calva, Conjure: [REPL / nREPL](https://phel-lang.org/documentation/tooling/repl/)

## compile vs eval vs run vs build

Pick by what you want back:

| Command | Input | Runs the code? | Output |
|---|---|---|---|
| `compile` | snippet / file / stdin | no | emitted **PHP source** (honors `optimizationLevel`) |
| `eval` | expression / stdin | yes | the **value** of the last form |
| `run` | file / namespace | yes | whatever the script prints / its side effects |
| `build` | the whole project | compiles (no run) | **PHP files** in the output dir, for deployment |

`eval` is a developer tool with full host access, not a sandbox. On using it as a
playground primitive, and why that needs isolation: [playground.md](playground.md).

## Optimization level

`withOptimizationLevel()` in `phel-config.php` sets the level `compile`, `run`,
`eval`, `test`, `repl` and `build` compile at (0 by default). Two things beat it:

- `PHEL_OPTIMIZATION_LEVEL` in the environment, for every command of that
  process and the processes it starts: `PHEL_OPTIMIZATION_LEVEL=0 phel test`.
  The value is a non-negative integer (`0`, `1`, `2`; anything above 2 acts as
  2). Any other value, such as `-1` or `fast`, exits 2 naming the variable
  (see [Environment variables](#environment-variables)).
- `phel build -O <level>`, for that build only. It beats the environment too.

`phel mutate` always runs at 0: it sets `PHEL_OPTIMIZATION_LEVEL=0` for itself
and its workers, because from level 1 up a caller inlines the body of the fn it
calls, and a mutant of that fn would never run.

## Environment variables

Phel reads these from the environment of every command:

| Variable | Kind | Effect |
|---|---|---|
| `PHEL_OPTIMIZATION_LEVEL` | number, 0 or more | The [optimization level](#optimization-level), over the config. |
| `PHEL_WARN_DEPRECATIONS` | switch | Print deprecation notices, like `--warn-deprecations`. |
| `PHEL_TEST_WORKERS` | number, 1 or more | Worker count for `test --parallel` and `mutate --parallel`, over the CPU count and its cap of 8. |
| `PHEL_DIR` | path | Where Phel keeps its state, instead of `<project>/.phel` ([project layout](project-layout.md)). |
| `PHEL_CACHE_DIR` | path | Where the compiled-code cache goes, over `PHEL_DIR`. |
| `PHEL_OPCACHE_REEXEC` | switch | Restart with the OPcache file cache for every command ([runtime](internals/runtime.md)). |
| `PHEL_NO_OPCACHE_REEXEC` | switch | Never restart with the OPcache file cache. |
| `CI` | switch, lenient | A `^:focus` left in fails `phel test`. |
| `NO_COLOR` | any value | No colour in Phel's own output. |
| `GITHUB_ACTIONS` | `true` | `phel test` adds the [`github` reporter](#tests-on-github-actions). |

A switch is on with `1`, `true`, `yes` or `on`, and off with `0`, `false`, `no`
or `off`, in any case. Surrounding whitespace is ignored, and an unset or empty
variable counts as not set. Any other value of a switch or a number stops the
command with exit 2 and the variable named on stderr:

```sh
$ PHEL_TEST_WORKERS=abc phel test
PHEL_TEST_WORKERS must be a whole number of at least 1, got "abc".
```

`CI` is the exception: CI services set it to their own values (`CI=woodpecker`),
so any value other than an off spelling turns it on, and it never stops a
command. `NO_COLOR` follows [no-color.org](https://no-color.org): any non-empty
value, `0` included, turns colour off. The OPcache switches are a tuning knob,
outside the stability promise.

## Exit codes

Every command exits `0` when it ran and found nothing to fail on, `1` when it ran
and found something, and `2` when it could not run as asked. A `2` names the
problem on stderr and leaves stdout empty. The rule and what it covers:
[ADR 0022](adr/0022-the-cli-machine-surface-is-under-semver.md).

| Command | `1` means | `2` means |
|---|---|---|
| `analyze` | a diagnostic is an error | a path is missing or unreadable |
| `lint` | a diagnostic is an error (warnings exit `0`) | a path is missing, an unknown `--format`, a `--config` file that is not there, no readable files, or the linter itself failed |
| `balance` | a file is unbalanced, or `--fix` could not repair it | a path is missing |
| `format` | `--dry-run` would change a file, or a file could not be formatted | a path is missing |
| `test` | a test failed or errored, or a `^:focus` run under `CI` or `--fail-on-focus` | a path is missing, an unknown `--reporter` |
| `bench` | a benchmark is slower than `--tolerance` against `--ref`, nothing to load in the given paths, or a benchmark threw | a path is missing, a `--ref` file that is not there, a bad `--ab` or `--pairs` value |
| `mutate` | the score is below `--min-msi` or `--min-covered-msi`, the suite fails without mutants, or a worker could not load | a bad option value, `--changed` outside a git repository |
| `run` | the program threw | no entry point, a path or namespace that is not there |
| `ns` | | a namespace that is not there |
| `profile` | the program threw | a path or namespace that is not there, an unknown `--format` or `--sort` |
| `build` | a namespace does not compile | a bad `-O` value |
| `index` | the `-o` file could not be written | a directory is missing |
| `config` | | an unknown `--format` |
| `doc` | | an unknown `--format`; no match is not an error |
| `compile`, `eval`, `export` | the code does not compile or throws | |
| `doctor` | a check failed | |
| `init` | a file could not be written | |
| `watch` | the watcher stopped on an error | |
| `agent-install` | `--check` found no installed docs, or a version other than the bundled one | an unknown platform |
| `api-daemon`, `lsp`, `nrepl` | the server could not start or stopped on an error | |

Every command exits `2`, before it starts, on a `PHEL_OPTIMIZATION_LEVEL`,
`PHEL_WARN_DEPRECATIONS` or `PHEL_TEST_WORKERS` it cannot read
([Environment variables](#environment-variables)). A program run by `run`,
`eval`, `test` or `repl` can exit with its own code, and a PHP fatal error exits
`255`.

These exit `1` where the rule says `2`, tracked in
[#3525](https://github.com/phel-lang/phel-lang/issues/3525): an unknown option
or command (Symfony's own code), `explain --format=xml`, `explain` with an
unknown code, `init --template=nope`, a bad `test --parallel`, `--repeat` or
`--seed` value, `test --changed` outside a git repository, and `watch` with no
readable path. `mutate` and `watch` skip a missing path instead of failing,
and `mutate` with no file left scores 100%. A few numeric options take any value
without complaint: `test --slowest`, `profile --top`, `nrepl --port`,
`watch --poll` and `--debounce`, and `bench --revs`, `--iterations`, `--warmup`
and `--tolerance` without `--ab`. `watch --backend` falls back to polling on an
unknown name.

## Output shapes

Field names and their meaning are covered by the
[stability policy](stability.md#command-line-interface): a field is only ever
added, so a reader ignores fields it does not know. Positions, paths and what
goes to stdout follow the rules under [Commands](#commands).

### Diagnostics

`lint --format=json` and `analyze` print a JSON array, and the `api-daemon`
`analyzeSource` method returns one. Each element:

| Field | Meaning |
|---|---|
| `code` | the lint rule (`phel/unused-require`), or for `analyze` the `PHELxxx` code |
| `severity` | `error`, `warning`, `info` or `hint` |
| `message` | the human text; match on `code`, not on this |
| `uri` | the file; `api-daemon` returns the `uri` the request sent |
| `startLine`, `startCol` | where the problem starts |
| `endLine`, `endCol` | where it ends; `endCol` is one past the last character |
| `errorCode` | the `PHELxxx` code behind the diagnostic, `null` for a lint-only rule |
| `suggestions` | the names a "did you mean" offers, possibly empty |
| `fix` | the catalog's advice, or `null` |

`lint --format=github` prints one workflow command per diagnostic:
`::error file=<path>,line=<n>,col=<n>,endLine=<n>,endColumn=<n>,title=<code>::<message>`.
The level is `error` for an error, `notice` for `info` and `hint`, and
`warning` otherwise. `file` is relative to the working directory.

### Project index

`phel index <dirs>...` prints a summary on stdout:
`{"namespaces": <count>, "definitions": <count>, "dirs": [<dirs as passed>]}`.
With `-o <file>` it also writes the full index there, the shape the `api-daemon`
`indexProject` method returns:

| Field | Meaning |
|---|---|
| `namespaces`, `definitions` | counts |
| `symbols` | a definition per `namespace/name` key |
| `references` | a list of locations per `namespace/name` key |
| `namespaceLocations` | the location of each namespace's `ns` form, keyed by namespace |

A definition carries `namespace`, `name`, `uri`, `line`, `col`, `kind` (`def`,
`defn`, `defmacro`, `defstruct`, `definterface`, `defprotocol`, `defexception`
or `unknown`), `signature` (a list of strings), `docstring`, `private` and
`deprecated` (the deprecation message, or `""`). A location carries `uri`,
`line`, `col`, `endLine` and `endCol`.

### `api-daemon`

A request is `{"id": <any>, "method": "<name>", "params": {...}}` on one line.
The reply is `{"id": <same>, "result": ...}`, or
`{"id": <same>, "error": {"code": <n>, "message": "..."}}` with `-32600` for a
missing method, `-32601` for an unknown one and `-32000` when the method
failed.

| Method | Params | Result |
|---|---|---|
| `analyzeSource` | `source`, `uri` | a list of [diagnostics](#diagnostics) |
| `indexProject` | `srcDirs` | the [project index](#project-index), kept for the methods below |
| `resolveSymbol` | `namespace`, `symbol` | a definition, or `null` |
| `findReferences` | `namespace`, `symbol` | a list of locations |
| `completeAtPoint` | `source`, `line`, `col` | a list of completions: `label`, `kind`, `detail`, `documentation` |
| `version` | none | the running Phel version, as in `v0.54.0`; `phel --version` prints it after `Phel `, and the LSP sends it as `serverInfo.version` |

### Other JSON outputs

| Command | Shape |
|---|---|
| `config --format=json` | one object with every `PhelConfig` key, described under [Commands](#commands) |
| `explain <code> --format=json` | `{"code", "title", "summary", "example", "fix"}`; with no code, a list of `{"code", "title"}`; an unknown code is `{"error": "..."}` |
| `doc --format=json` | a list of `{"namespace", "name", "requireNs", "require", "signatures", "doc", "description", "example", "githubUrl", "docUrl"}`; `name` is `namespace/name` with the namespace's short label, `requireNs` the namespace to require, `require` the `(:require ...)` form, or `null` when nothing needs requiring. No match is `[]` |
| `mutate --format=json` | `baselineSeconds`; `coverage` (the driver that matched tests to lines, or `""`); `totals` with `mutants`, `killed`, `survived`, `notCovered`, `errors`, `timeouts`, `msi`, `coveredMsi`; `mutants`, a list of `file`, `line`, `column`, `namespace`, `definition`, `mutator`, `description`, `verdict` (`killed`, `survived`, `error`, `timeout` or `not-covered`), `seconds`, `detail`, `diff` |

### Test reporters

`test --reporter=tap` prints TAP version 13, `--reporter=junit-xml` one JUnit
XML document (a `<testsuite>` per namespace, a `<testcase>` per assertion with
`file` and `line`), and `--reporter=github` GitHub workflow commands. The other
reporters (`default`, `testdox`, `dot`) are human text.

### nREPL

`phel nrepl` writes the port it bound to `.nrepl-port` in the working
directory and removes the file when it stops. Next to the standard nREPL ops it
answers `reload` (param `all`: `true` or `1` reloads every namespace, else only
the changed ones) and `run-tests` (param `ns`, optional `var` for one test).

## Error codes

A compile or runtime failure prints a `[PHELxxx]` code in front of its message.
`phel explain` turns that code back into prose:

```sh
phel explain PHEL001      # what it means, a minimal example, the fix
phel explain 1            # same code: the prefix and leading zeroes are optional
phel explain              # every code, one line each
phel explain phel/unused-require        # a lint rule code, as phel lint prints it
phel explain PHEL001 --format=json      # the same entry as JSON
```

`phel lint --format=json` and `phel analyze` report every diagnostic with the
same fields, listed under [Diagnostics](#diagnostics).

An unknown code exits 1. The text comes from the same catalog the pages under
`docs/errors/` are generated from, so the terminal and the docs cannot drift.

## Errors from a built app

`build` writes a `.php.map` and a `.phel` beside every compiled file, so a stack
trace can be reported against the source you wrote. Nothing installs that
reporting for you, and PHP's default handler knows only the generated file:

```
Uncaught RuntimeException: boom in out/app/main.php:22
#0 out/app/main.php(43): Phel\Lang\AbstractFn@anonymous->__invoke(2)
```

One line in the entry point replaces it with the Phel reading:

```php
\Phel::installExceptionHandler(__DIR__);
```

```
RuntimeException: boom
in out/app/main.phel:3 (gen: out/app/main.php:22)

#0 out/app/main.phel:6 (gen: out/app/main.php:43) : (app.main/level-three 2)
#1 out/app/main.phel:9 (gen: out/app/main.php:64) : (app.main/level-two 1)
```

It follows PHP's own rules about where a report goes rather than inventing new
ones: the error log when `log_errors` is on, and output only when
`display_errors` is on, so a production response body stays clean. The process
still exits `255`. The log copy carries no ANSI escapes; the `display_errors`
copy is coloured unless `NO_COLOR` is set.

If the trace cannot be mapped, the plain PHP rendering goes out instead: the
reporter never replaces the exception it exists to report.

## Common workflows

### Start a project

```sh
phel init my-app          # scaffold config + main + test
phel run                  # run the auto-detected entry point
phel completion zsh       # (optional) enable tab-completion
```

### The dev loop

```sh
phel test --watch         # re-run tests on change
phel watch                # hot-reload namespaces instead
phel format --dry-run     # check formatting; --exclude='src/*_data.phel' skips generated files
```

`phel format` walks `format-dirs`; a glob passed as `--exclude` (repeatable) or
listed under the `format-exclude` config key is skipped, matched against each
path as found and relative to the working directory, with `*` spanning
directories. Use it for baked data files and vendored trees that live beside
their consumers.

Both reuse the compiled-code cache, so a one-file edit recompiles only the
affected namespaces. `phel repl` (or `phel nrepl` from your editor) for
interactive exploration.

### Ship it

```sh
phel build                # compile the project to PHP in the output dir
phel doctor               # verify runtime + OPcache cold-start setup
```

### Tests on GitHub Actions

`phel test` adds the `github` reporter next to the default one whenever
`GITHUB_ACTIONS` is `true` and no `--reporter` was given: every failed or
errored assertion becomes an inline `::error file=...,line=...` annotation on
the pull request diff, each namespace is a collapsible `::group::`, a run
narrowed by `^:focus` is a `::warning`, and the counts are appended to the job's
step summary (`$GITHUB_STEP_SUMMARY`). `--reporter=github` selects it anywhere;
an explicit `--reporter` list is never extended.

## Discoverability

- `phel <command> --help` — description, options, and at least one example.
- `phel doc <fn>` — docstring, signature, and example for any function.
- `phel completion <shell>` — tab-complete commands, options, namespaces.

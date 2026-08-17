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
| `analyze` | Run semantic analysis on a single Phel source file and emit JSON diagnostics |
| `api-daemon` | Long-running JSON-RPC daemon exposing the Api semantic-analysis facade over stdio (for tooling) |
| `balance` | Report unbalanced `()`, `[]`, `{}` in Phel files; append the missing closers with `--fix` |
| `bench` | Run the `defbench` benchmarks (all of them, or the files/namespaces you pass) |
| `build` `b` | Build the current project: compile every namespace to PHP in the output dir |
| `cache:clear` | Clear the temp and cache directories |
| `cache:warm` | Pre-resolve all module classes and warm the cache for production |
| `completion` | Dump the shell completion script for bash, zsh, or fish |
| `compile` | Compile a Phel snippet/file/stdin and print the emitted PHP — does not evaluate |
| `config` | Show the effective Phel configuration and where it comes from |
| `doc` | Display the docs for any/all Phel functions |
| `doctor` | Check system requirements (PHP, extensions, OPcache cold-start) for the Phel CLI |
| `eval` `e` | Evaluate a Phel expression (or stdin) and print the result |
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

Gacela registers a few more through `Console/Infrastructure/Command/FrameworkCommands`:
`debug:container`, `debug:dependencies`, `debug:modules`, `list:modules`,
`profile:report` and `validate:config`. They inspect the module wiring rather than
your Phel code, and `phel list` shows them alongside the commands above.

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
\Phel\Phel::installExceptionHandler(__DIR__);
```

```
RuntimeException: boom
in out/app/main.phel:3 (gen: out/app/main.php:22)

#0 out/app/main.phel:6 (gen: out/app/main.php:43) : (app\main\level-three 2)
#1 out/app/main.phel:9 (gen: out/app/main.php:64) : (app\main\level-two 1)
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

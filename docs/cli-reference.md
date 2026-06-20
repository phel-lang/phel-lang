# CLI Reference & DX Guide

A single map of every `phel` command, the common workflows, and the
edit→test dev loop. Run `phel <command> --help` for the full options of any
command (each ships a usage example), and `phel completion bash|zsh|fish` to
enable tab-completion (see the [README](../README.md#enable-shell-completion-optional)).

> User-facing tutorials live on [phel-lang.org](https://phel-lang.org/documentation/tooling/cli-commands/);
> this page is the quick reference kept next to the code.

## Commands

| Command | Purpose |
|---|---|
| `agent-install` | Install agent skill files (Claude, Cursor, Codex, Gemini, Copilot, Aider) into the current project |
| `analyze` | Run semantic analysis on a single Phel source file and emit JSON diagnostics |
| `api-daemon` | Long-running JSON-RPC daemon exposing the Api semantic-analysis facade over stdio (for tooling) |
| `build` `b` | Build the current project: compile every namespace to PHP in the output dir |
| `cache:clear` | Clear the temp and cache directories |
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
| `nrepl` | Start an nREPL server for editor tooling (bencode over TCP) |
| `ns` `loaded-ns` | List all loaded namespaces, or inspect one |
| `profile` | Profile a script: per-fn call counts/timings + compile-phase costs |
| `repl` | Start an interactive REPL |
| `run` `r` | Run a Phel file or namespace (auto-detects the entry point) |
| `test` `t` | Run the test suite (all tests, or the files/namespaces you pass) |
| `watch` | Watch Phel files and reload changed namespaces on change |

## compile vs eval vs run vs build

These four overlap; pick by what you want back:

| Command | Input | Runs the code? | Output |
|---|---|---|---|
| `compile` | snippet / file / stdin | no | emitted **PHP source** (honors `optimizationLevel`) |
| `eval` | expression / stdin | yes | the **value** of the last form |
| `run` | file / namespace | yes | whatever the script prints / its side effects |
| `build` | the whole project | compiles (no run) | **PHP files** written to the output dir, for deployment |

Rule of thumb: `eval` to check a value, `run` to execute a script, `compile`
to inspect generated PHP for one form, `build` to produce deployable PHP for
the entire project.

## Common workflows

### Start a project

```sh
phel init my-app          # scaffold config + main + test
phel run                  # run the auto-detected entry point
phel completion zsh       # (optional) enable tab-completion
```

### The dev loop

```sh
phel test --watch         # re-run tests on every change (incremental cache reuse)
# or, for hot-reloading namespaces:
phel watch
```

Both reuse the compiled-code cache, so a one-file edit recompiles only the
affected namespaces. Use `phel repl` (or `phel nrepl` from your editor) for
interactive exploration.

### Ship it

```sh
phel build                # compile the project to PHP in the output dir
phel doctor               # verify runtime + OPcache cold-start setup
```

## Discoverability

- `phel <command> --help` — description, options, and at least one example.
- `phel doc <fn>` — docstring, signature, and example for any function.
- `phel completion <shell>` — tab-complete commands, options, namespaces.

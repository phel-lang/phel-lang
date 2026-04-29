# Phel Documentation

## Start here

1. [Quick Start](quickstart.md) — install, REPL, first script
2. [Common Patterns](patterns.md) — idioms used every day
3. [PHP/Phel Interop](php-interop.md) — call PHP from Phel and back

Coming from Clojure? Read [Clojure Migration](clojure-migration.md).

## Language

- [Reader Shortcuts](reader-shortcuts.md) — `#inst`, `#regex`, `#php`, anonymous fn `#(...)`
- [Reader Conditionals](reader-conditionals.md) — `.cljc` portability
- [Data Structures](data-structures-guide.md) — vectors, maps, sets, transients
- [Lazy Sequences](lazy-sequences.md) — `lazy-seq`, infinite seqs, realization
- [Transducers](transducers.md) — composable transformations
- [Pattern Matching](match-guide.md) — `match` with guards and destructuring

## Tooling

- [REPL & nREPL](nrepl-guide.md) — editor integration over bencode/TCP
- [Language Server](lsp-guide.md) — hover, definition, references, completion
- [Linter](lint-guide.md) — `phel lint` rules and config
- [Watch](watch-guide.md) — hot-reload changed namespaces
- [CLI Builder](cli-guide.md) — build CLIs with `phel\cli`
- [Performance](performance.md) — opcache setup, cache reset
- [Mocking](mocking-guide.md) — test seams for PHP calls

## Modules

- [Async](async-guide.md) — fibers, promises, futures, AMPHP
- [Schema](schema-guide.md) — validate, coerce, generate
- [AI](ai-guide.md) — `chat-with-tools`, OpenAI tool use

## Apps

- [Framework Integration](framework-integration.md) — Laravel, Symfony, framework-less
- [Examples](examples/README.md) — runnable single-file samples

## Internals

Start with the [internals overview](internals/README.md) for a guided path.

- [Architecture](internals/architecture.md) — modules, Gacela pattern, dependency map
- [Compiler](internals/compiler.md) — phases, AST, emitter
- [Special forms](internals/special-forms.md) — full list, dispatch, how to add one
- [Macros](internals/macros.md) — `macroexpand`, quasiquote, gensym
- [Runtime](internals/runtime.md) — `Lang/`, persistent collections, `Registry`
- [FAQ](internals/faq.md) — questions grouped by reader
- [Benchmarks](internals/benchmarks.md) — PHPBench setup
- [Migration: backslash to dot](migration/backslash-to-dot.md)

## AI agents

- [resources/agents/](../resources/agents/README.md) — Claude Code, Cursor, Codex, Gemini, Copilot, Aider
- [agent-docs](agent-docs.md) · [agent-metrics](agent-metrics.md)

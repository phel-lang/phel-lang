# Phel Documentation

This `docs/` tree is **contributor-facing**: it covers the repository internals,
agent tooling, and project layout. User-facing guides (language, tooling,
modules, deployment) live on **[phel-lang.org](https://phel-lang.org/documentation/)**.

## What lives here

- [Specification](spec/README.md): the normative language surface and the deliberate Clojure divergences
- [Stability policy](stability.md): what a version number promises, which PHP symbols semver covers, and the 1.x deprecation and PHP support policies
- [Architecture decisions](adr/README.md): why the repository is shaped the way it
  is, one record per decision, including the ones that look like oversights
- [CLI Reference & DX Guide](cli-reference.md): every command, the dev loop, and compile vs eval vs run vs build
- [Project Layout](project-layout.md): the `.phel/` directory and runtime state
- [Internals](internals/README.md): architecture, compiler phases, AST, emitter,
  macros, runtime, FAQ, benchmarks
- [Upgrading 0.49 to 1.0](migration/upgrade-0.49-to-1.0.md): what to change, and how to find out whether you need to change anything
- [Migration: the currently deprecated surface](migration/deprecated-surface.md): every live deprecation, its replacement, and how it announces itself
- [Migration: backslash to dot](migration/backslash-to-dot.md)
- [Migration: removed deprecated core functions](migration/removed-deprecated-core-fns.md)
- [Examples](examples/README.md): runnable single-file samples
- [Web playground](playground.md): design notes and the language-side spike for "try Phel in the browser"
- [AI agents](../resources/agents/README.md): Claude Code, Cursor, Codex, Gemini, Copilot, Aider
- [agent-docs](agent-docs.md) · [agent-metrics](agent-metrics.md)

## PHP interop, in one paragraph

Phel has two spellings for reaching PHP, and the Clojure-style one is the idiomatic
default:

```phel
(new \DateTime "2020-01-01")   ; or (\DateTime. "2020-01-01")
(.format d "Y")                ; instance method
(.-prop obj)                   ; property
(\DateTime/createFromFormat "Y-m-d" "2020-01-01")   ; static method
```

Each of those desugars to a `php/*` special form (`php/new`, `php/->`, `php/::`)
before analysis. That layer is the compilation target, and it came first, which is
why older code is written in it — but it is **deprecated as source**: every position
it once had to itself now has a shorthand. Write the shorthand.

Chaining threads with plain `->`, mixed method and property chains included:

```phel
(-> (new \DateTimeImmutable "2024-03-10") (.modify "+1 day") (.format "Y-m-d"))
```

The full expansion table, including `\C/m` and `\C/.m` in value position, is in
[the language surface spec](spec/language-surface.md#interop-shorthands). The
complete guide with runnable examples is on the website:
<https://phel-lang.org/documentation/php-interop/>. A runnable sample lives in
[examples/09_php-integration.phel](examples/09_php-integration.phel).

## User-facing guides moved to phel-lang.org

The guides below were ported to the website and removed from this repo. Use these
links instead:

| Topic | Website |
|---|---|
| Getting started / quickstart | https://phel-lang.org/documentation/getting-started/ |
| Configuration | https://phel-lang.org/documentation/configuration/ |
| PHP interop | https://phel-lang.org/documentation/php-interop/ |
| Coming from Clojure | https://phel-lang.org/documentation/guides/coming-from-clojure/ |
| CLI commands | https://phel-lang.org/documentation/tooling/cli-commands/ |
| Lint / profile / watch | https://phel-lang.org/documentation/tooling/cli-commands/ |
| Data structures | https://phel-lang.org/documentation/language/data-structures/ |
| Cookbook / patterns | https://phel-lang.org/documentation/guides/cookbook/ |
| Editor support (LSP) | https://phel-lang.org/documentation/tooling/editor-support/ |
| REPL / nREPL | https://phel-lang.org/documentation/tooling/repl/ |
| Testing / mocking / parallel tests | https://phel-lang.org/documentation/testing/ |
| Debugging (dbg, tap>, traces, Xdebug) | https://phel-lang.org/documentation/debugging/ |
| Pattern matching (`match`) | https://phel-lang.org/documentation/language/functions-and-recursion/ |
| Reader shortcuts | https://phel-lang.org/documentation/language/reader-shortcuts/ |
| Reader conditionals | https://phel-lang.org/documentation/language/reader-conditionals/ |
| Numeric tower | https://phel-lang.org/documentation/language/numeric-tower/ |
| Lazy sequences | https://phel-lang.org/documentation/language/lazy-sequences/ |
| Transducers | https://phel-lang.org/documentation/language/transducers/ |
| Async / fibers | https://phel-lang.org/documentation/language/async/ |
| Schema | https://phel-lang.org/documentation/guides/schema/ |
| AI | https://phel-lang.org/documentation/guides/ai/ |
| Data interchange formats | https://phel-lang.org/documentation/guides/data-formats/ |
| Performance | https://phel-lang.org/documentation/performance/ |
| Deployment | https://phel-lang.org/documentation/deployment/ |
| Framework integration | https://phel-lang.org/documentation/web/framework-integration/ |

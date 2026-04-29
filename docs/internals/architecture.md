# Architecture

How the codebase is organised and why. If [compiler.md](compiler.md) is "what each pipeline stage does", this page is "where each stage lives, who depends on whom, and how to find your way around `src/php/`".

## Top-level layout

```
src/php/         Compiler, runtime, CLI (PHP, PSR-4 prefix Phel\)
src/phel/        Core library written in Phel: core, string, html, http, json, test
tests/php/       PHPUnit unit + integration tests (compiler-side)
tests/phel/      Phel test files (core-side, run via `./bin/phel test`)
build/           PHAR build, release tooling
resources/       Agent prompts, docs assets, schemas
```

The compiler is PHP. The standard library that ships with Phel is itself written in Phel and lives next door in `src/phel/` — see `phel/core.phel` for the bulk of it.

## Modules under `src/php/`

Every directory is a [Gacela](https://gacela-project.com/) module. Each one owns one concern, communicates with others only via its `Facade`, and pulls in collaborators with a `DependencyProvider`. New code lives in the module that already owns the concern, not somewhere "more general".

| Module | Purpose |
|--------|---------|
| `Lang/` | Runtime type system: persistent collections, `Symbol`, `Keyword`, `Variable`, `Registry`. **Foundational, no facade.** |
| `Compiler/` | Lexer → Parser → Reader → Analyzer → Emitter → Evaluator. See [compiler.md](compiler.md). |
| `Printer/` | Pretty-print Phel values; readable vs. non-readable forms. |
| `Run/` | `phel run`, the interactive REPL (`Run/Domain/Repl/`), namespace bootstrap (`Run/Runtime/`), runtime-side autoloader for compiled namespaces. |
| `Build/` | Compiles a project to PHP on disk; resolves namespace dependency order. |
| `Command/` | CLI command registry shared across `phel` subcommands. |
| `Console/` | Symfony Console wiring; the binary entry points. |
| `Api/` | Programmatic access to documented Phel symbols (used by `doc`, `lsp`, generators). |
| `Interop/` | Generates PHP wrapper classes so PHP code can call Phel from a normal IDE. |
| `Lint/` | `phel lint` — runs over parse trees from `Compiler/`. |
| `Formatter/` | Pretty-prints `.phel` source by walking the parse tree. |
| `Lsp/` | Language Server Protocol over stdio. Reuses `Compiler/`, `Api/`. |
| `Nrepl/` | nREPL server (bencode/TCP). Reuses `Compiler/`, `Run/`. |
| `Watch/` | Filesystem watcher for hot reload. |
| `HttpClient/` | Thin client used by `phel\http`. |
| `Fiber/` | Fibers + futures runtime helpers. |
| `Filesystem/` | File I/O abstraction (test seam for the rest of the compiler). |
| `Config/` | `phel-config.php` loading and access. |
| `Shared/` | Tiny cross-module utilities (only what truly has no better home). |
| `Phel.php` | Static facade used by *generated PHP*: `\Phel::addDefinition(...)`, `\Phel::keyword(...)`, etc. |

## The Gacela pattern

Every non-foundational module follows the same skeleton. `Run/` is a representative example:

```
Run/
├── RunFacade.php            ← public API (the only thing other modules import)
├── RunFactory.php           ← wires up its own classes
├── RunConfig.php            ← typed config
├── RunProvider.php          ← declares dependencies on other facades
├── Application/             ← orchestration / use cases
├── Domain/                  ← pure logic, value objects, interfaces
├── Infrastructure/          ← adapters: filesystem, console, env
└── Runtime/                 ← module-specific runtime helpers (here: Phel namespace bootstrap)
```

Rules:

- **Never instantiate a class from another module directly.** Always go through the other module's `Facade`.
- **Never reach into another module's `Domain/`.** That's its private surface.
- **Add a method to your own facade** before you reach for someone else's.
- The constructor of a `Facade` is empty; collaborators come from the `Factory`/`Provider`.

The provider declares dependencies as facade constants. Example from `RunProvider`:

```php
public const string FACADE_COMPILER = 'FACADE_COMPILER';

#[Provides(self::FACADE_COMPILER)]
public function compilerFacade(Container $container): CompilerFacade
{
    return $container->getLocator()->getRequired(CompilerFacade::class);
}
```

Every module ships its own `CLAUDE.md` documenting public API, dependencies, and constraints. Read that first when you touch a module.

## Dependency map (high-level)

```
                          Console / Command
                                   │
                ┌──────────┬───────┼─────────┬───────────┐
                ▼          ▼       ▼         ▼           ▼
               Run       Build   Lint   Formatter      Watch
                │          │       │         \         /
                └──────────┴───┬───┘          \       /
                               ▼               ▼     ▼
                        Compiler  ◄────── Api  ◄── Lsp / Nrepl / Watch
                            │
                            ▼
                          Lang (foundational)
                            ▲
                            │
                       Printer (rendering)
```

A few load-bearing edges:

- **Everything depends on `Compiler/`** for parsing/analysing Phel.
- **Everything depends on `Lang/`** for the runtime types.
- **`Lang/` is a leaf**: no inbound dependencies on other modules except a single `__toString()` link to `Printer\Printer::readable()` (see `Lang/CLAUDE.md`).
- **`Lsp/`, `Nrepl/`, `Watch/`** are tools, not part of the core compile path; they reuse the compiler facade.

## Compile-time vs. runtime

This trips up almost everyone the first time. There are two PHP processes worth keeping straight in your head:

1. **The compiler process** — what `composer test-compiler` exercises and what runs when you call `CompilerFacade::compile()`. This process holds the `GlobalEnvironment`, the macro definitions, and the `TypeFactory`/`Registry` singletons in memory while it walks your forms.
2. **The runtime process** — what executes the PHP that the compiler emitted. It only sees `\Phel::addDefinition(...)` calls, `\phel\core\…` functions, and the `Lang/` types. It does not see the analyzer, the parser, or the special-form handlers.

Compile time and runtime can be the same physical PHP process (REPL, `phel run`) or different (cached `.php` files served by a framework). The boundary is conceptual: "is this code being analysed, or is it being executed?"

## Where to add a feature

| You want to… | Touch this |
|--------------|------------|
| Add a new reader macro (`#foo`) | `Lang/TagHandlers/`, register in `TagRegistry` |
| Add a new special form | `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/` + matching `NodeEmitter`. See [special forms](special-forms.md). |
| Add a new core function in Phel | `src/phel/core/...` |
| Add a CLI subcommand | `Run/` or its own module + `Command/` registration |
| Add an LSP capability | `Lsp/Domain/` |
| Add a linter rule | `Lint/Domain/Analyzer/` |

When in doubt, run `grep -r "FACADE_" src/php/<Module>/<Module>Provider.php` to see what a module already pulls in — that's the cheapest map of how it talks to the rest of the system.

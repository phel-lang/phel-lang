# Architecture

## Layout

```
src/php/      Compiler, runtime, CLI (PSR-4 prefix Phel\)
src/phel/     Stdlib in Phel: core, string, html, http, json, test
tests/php/    PHPUnit unit + integration
tests/phel/   Phel tests via `./bin/phel test`
build/        PHAR + release tooling
```

Compiler is PHP. Stdlib is Phel: bulk in `src/phel/core.phel`.

## Modules

Every directory under `src/php/` is a [Gacela](https://gacela-project.com/) module. `Facade` for public API, `Provider` for cross-module deps, `Factory` for internal wiring.

| Module | Purpose |
|--------|---------|
| `Lang/` | Runtime types: persistent collections, `Symbol`, `Keyword`, `Variable`, `Registry`. Foundational, no facade. |
| `Compiler/` | Lex → Parse → Read → Analyze → Emit → Eval. See [compiler.md](compiler.md). |
| `Printer/` | Render Phel values. |
| `Run/` | `phel run`, REPL (`Run/Domain/Repl/`), namespace bootstrap (`Run/Runtime/`). |
| `Build/` | Compile project to PHP on disk; namespace dependency order. |
| `Command/` | CLI command registry. |
| `Console/` | Symfony Console wiring; binary entry. |
| `Api/` | Programmatic access to documented symbols (used by `doc`, `lsp`). |
| `Interop/` | Generates PHP wrappers so PHP can call Phel from an IDE. |
| `Lint/` | `phel lint` over parse trees. |
| `Formatter/` | Pretty-prints `.phel`. |
| `Lsp/` | LSP over stdio. |
| `Nrepl/` | nREPL bencode/TCP. |
| `Watch/` | Hot reload watcher. |
| `HttpClient/`, `Fiber/`, `Filesystem/`, `Config/`, `Shared/` | Helpers. |
| `Phel.php` | Static facade called by *emitted* PHP: `\Phel::addDefinition(...)`, `\Phel::keyword(...)`. |

## Gacela skeleton

```
Run/
├── RunFacade.php       public API
├── RunFactory.php      internal wiring
├── RunConfig.php       typed config
├── RunProvider.php     cross-module deps
├── Application/        orchestration
├── Domain/             pure logic, value objects
└── Infrastructure/     adapters
```

Rules:

- Never instantiate another module's class directly. Go via Facade.
- Never reach into another module's `Domain/`.
- Add a method to your facade before consuming someone else's internals.

Provider declares deps as facade constants:

```php
#[Provides(self::FACADE_COMPILER)]
public function compilerFacade(Container $container): CompilerFacade
{
    return $container->getLocator()->getRequired(CompilerFacade::class);
}
```

Each module ships `CLAUDE.md` with API + constraints. Read it before editing.

## Dependency map

```
              Console / Command
                     │
   ┌────┬─────┬──────┼──────┬──────┐
   ▼    ▼     ▼      ▼      ▼      ▼
  Run  Build Lint Formatter Watch  …
   └────┴────┬┴──────┘
            ▼
        Compiler ◄── Api ◄── Lsp / Nrepl
            │
            ▼
          Lang  ◄── Printer
```

- Everything depends on `Compiler/` and `Lang/`.
- `Lang/` is a leaf (one outbound `__toString()` to `Printer`).
- `Lsp/`, `Nrepl/`, `Watch/` reuse the compiler facade; not on the compile path.

## Compile-time vs runtime

- **Compile**: `CompilerFacade::compile()`. Holds `GlobalEnvironment`, macros, `TypeFactory`/`Registry`.
- **Runtime**: executes emitted PHP. Sees only `\Phel::*`, `\phel\core\*`, `Lang/` types.

Same physical PHP process (REPL, `phel run`) or different (cached files in a framework). Boundary is "being analysed" vs "being executed".

## Where to add a feature

| Want | Touch |
|------|-------|
| Reader macro `#foo` | `Lang/TagHandlers/` + `TagRegistry` |
| Special form | `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/` + matching `NodeEmitter` ([special-forms.md](special-forms.md)) |
| Core fn in Phel | `src/phel/core/…` |
| CLI subcommand | new module or `Run/` + `Command/` registration |
| LSP capability | `Lsp/Domain/` |
| Lint rule | `Lint/Domain/Analyzer/` |

`grep -r "FACADE_" src/php/<Module>/<Module>Provider.php` shows a module's deps.

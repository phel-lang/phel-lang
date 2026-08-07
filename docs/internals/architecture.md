# Architecture

## Layout

```
src/php/      Compiler, runtime, CLI (PSR-4 prefix Phel\)
src/phel/     Stdlib in Phel: core, string, html, http, json, test
tests/php/    PHPUnit unit + integration
tests/phel/   Phel tests via `./bin/phel test`
build/        PHAR + release tooling
```

Compiler is PHP. Stdlib is Phel: `src/phel/core.phel` bootstraps the core namespace by loading sub-files from `src/phel/core/`.

`src/php/` modules use PSR-4 prefix `Phel\`. The global `\Phel` runtime facade lives at `src/Phel.php` (distinct from the `Phel\Phel` bootstrap in `src/php/Phel.php`).

## Modules

Every directory under `src/php/` is a module. Most follow the [Gacela](https://gacela-project.com/) pattern: `Facade` for public API, `Provider` for cross-module deps, `Factory` for internal wiring. The Gacela pillars themselves are internal PHP wiring, not user-facing API; public consumers enter through facades and documented Shared contracts. `Lang/`, `Shared/`, `Config/` and `HttpClient/` are leaves with no Gacela wiring; their `CLAUDE.md` says "No Gacela Pattern".

| Module | Purpose |
|--------|---------|
| `Lang/` | Runtime types: persistent collections, `Symbol`, `Keyword`, `Atom`, `Registry`. Foundational, no facade. |
| `Compiler/` | Lex → Parse → Read → Analyze → Simplify → Emit → Eval. See [compiler.md](compiler.md). |
| `Shared/Printer/` | Render Phel values (lives in `Shared/`, not a top-level module). |
| `Run/` | `phel run`, REPL (`Run/Domain/Repl/`), namespace bootstrap (`Run/Runtime/PhelSourceLoader.php`). |
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
| `Profile/` | `phel profile` instrumentation and report. |
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
- Declare inherited services explicitly: Facade `getFactory()` maps to its Factory, while Factory and Provider `getConfig()` map to the module Config. This avoids Gacela's deprecated source/docblock fallback.

```php
#[ServiceMap(method: 'getFactory', className: RunFactory::class)]
final class RunFacade extends AbstractFacade
{
}
```

Provider declares dependencies with Gacela 2.0 `#[Provides]`. Facade dependencies are keyed by the contract the consuming factory requests:

```php
#[Provides(CompilerFacadeInterface::class)]
public function compilerFacade(Container $container): CompilerFacadeInterface
{
    return $container->getLocator()->getRequired(CompilerFacade::class);
}
```

Use string keys only for non-facade services such as `ConsoleProvider::LAZY_COMMANDS` and `CommandProvider::PHP_CONFIG_READER`. The one concrete-facade exception is `LspProvider::FACADE_LINT`, because `Lint` has no Shared facade contract yet.

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
          Lang
```

- Everything depends on `Compiler/` and `Lang/`.
- `Lang/` and `Shared/` are leaves for every other module, but they reference each other: `Shared/Printer/` prints `Lang/` values, and `Lang/TypeStringifier` calls `Printer::readable()`. This is one of the four deliberate cycles listed in `src/php/CLAUDE.md` and pinned by `tests/php/Unit/Architecture/ModuleDependencyCycleTest.php`.
- `Lsp/`, `Nrepl/`, `Watch/` reuse the compiler facade; not on the compile path.

## Compile-time vs runtime

- **Compile**: `CompilerFacade::compile()`. Holds `GlobalEnvironment`, macros, `TypeFactory`/`Registry`.
- **Runtime**: executes emitted PHP. Sees only `\Phel::*` (with dot-form registry keys like `"phel.core"`) and `Lang/` types.

Same physical PHP process (REPL, `phel run`) or different (cached files in a framework). Boundary is "being analysed" vs "being executed".

## Where to add a feature

| Want | Touch |
|------|-------|
| Reader macro `#foo` | `Lang/TagHandlers/` + `TagRegistry` |
| Special form | `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/` + matching `NodeEmitter` ([special-forms.md](special-forms.md)) |
| Core fn in Phel | `src/phel/core/…` |
| CLI subcommand | new module or `Run/` + `Command/` registration |
| LSP capability | `Lsp/Domain/` |
| Lint rule | `Lint/Application/Rule/` |

`rg '#\[Provides' src/php/<Module>/<Module>Provider.php` shows a module's Gacela-provided deps.

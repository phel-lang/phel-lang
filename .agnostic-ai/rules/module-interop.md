---
name: module-interop
description: 'Load when editing src/php/Interop: PHP wrapper generation for Phel functions marked `^{:export true}`.'
scope: src/php/Interop
---

# Interop Module

Generates PHP wrapper classes for Phel functions marked `^{:export true}`, so PHP code can call them.

## Public API (Facade)

| Method | Purpose |
|--------|---------|
| `generateExportCode(): list<Wrapper>` | Run the full export pipeline (`ExportCodeGenerator`) |
| `writeLocatedException(output, CompilerException)` | Delegate to Command facade |
| `writeStackTrace(output, Throwable)` | Delegate to Command facade |

## Dependencies

| Provider key | Used for |
|--------------|----------|
| `CommandFacadeInterface::class` | error / exception output |
| `BuildFacadeInterface::class` | namespace extraction, compilation, evaluation |

One non-facade edge, **Config**: `InteropConfig` imports `PhelConfig` and `PhelExportConfig` and reads the `export` config: `getExportDirectories()`, `prefixNamespace()` (default `PhelGenerated`), `getExportTargetDirectory()` (default `src/PhelGenerated`).

## Structure

| Path | Role |
|------|------|
| `Domain/ExportFinder/FunctionsToExportFinder` | Finds `^{:export true}` fns in export dirs |
| `Domain/Generator/Builder/CompiledPhpMethodBuilder` | Renders one wrapper method via reflection + token template |
| `Domain/Generator/Builder/CompiledPhpClassBuilder` | Wraps methods into a namespaced class |
| `Domain/DirectoryRemover/DirectoryRemover` | Wipes target dir before regen |
| `Domain/ReadModel/FunctionToExport` | Value object (`Wrapper` lives in `Phel\Shared\Interop`) |
| `PhelCallerTrait` | Mixed into every wrapper; `callPhel(ns, def, ...args)` |
| `ExportedDefinitionNotFoundException` | Raised by the trait on failed resolution; separates "namespace not loaded" from "stale wrapper" |

## Key Constraints

- Only `^{:export true}` fns are exported.
- Wrappers call `\Phel::assertBuiltWith(VersionResolver::current())` after the namespace declaration and before the class. They need the exact generating Phel version; run `phel export` after changing it.
- The export target directory is wiped and regenerated each run.
- Phel ns → PHP: hyphens become CamelCase (`my-lib` → `MyLib`); method names camelCase the fn name (`my-fn` → `myFn`).
- `CompiledPhpMethodBuilder` reflects the compiled fn class's `BOUND_TO` constant and `__invoke` signature; do not bypass: params, return type and ns come from there. Native param/return types (from `:tag`) are mirrored into the wrapper signature plus an `@param`/`@return` docblock. Multi-arity fns reflect an untyped `__invoke($a0 = null, ...)` that dispatches on `func_num_args()`, so the wrapper forwards `...$args` (any optional non-variadic param triggers it; passing every param would always pick the widest arity) and its `@return` comes from the return `:tag` that `FunctionToExport` carries from definition metadata. Fns without type info keep a docblock-free `mixed` wrapper.
- `^{:php/attr [...]}` on an exported `defn` is threaded through `FunctionToExport` and rendered by `Phel\Shared\PhpAttributeRenderer` as PHP 8 attributes above the wrapper method (e.g. `#[\…\Route('/p')]`).
- `PhelCallerTrait` caches resolved definitions in a process-wide static keyed by `namespace::definitionName`, never invalidated. Safe for CLI / per-request FPM; a wrapper keeps the originally resolved definition if the runtime redefines it mid-process.
- A wrapper resolves, never loads: the host must evaluate the namespace first. Otherwise the trait raises `ExportedDefinitionNotFoundException` naming the source spelling of the namespace (`my-app.billing`, not the munged `my_app.billing`) and which of the two fixes applies.

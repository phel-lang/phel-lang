# Interop Module

Generates PHP wrapper classes for Phel functions marked `^{:export true}`, so PHP code can call them.

## Public API (Facade)

| Method | Purpose |
|--------|---------|
| `generateExportCode(): list<Wrapper>` | Run the full export pipeline (`ExportCodeGenerator`) |
| `writeLocatedException(output, CompilerException)` | Delegate to Command facade |
| `writeStackTrace(output, Throwable)` | Delegate to Command facade |

## Dependencies

| Facade (`InteropProvider::*`) | Used for |
|--------|----------|
| `FACADE_COMMAND` | error / exception output |
| `FACADE_BUILD` | namespace extraction, compilation, evaluation |

`InteropConfig` reads the `export` config: `getExportDirectories()`, `prefixNamespace()` (default `PhelGenerated`), `getExportTargetDirectory()` (default `src/PhelGenerated`).

## Structure

| Path | Role |
|------|------|
| `Domain/ExportFinder/FunctionsToExportFinder` | Finds `^{:export true}` fns in export dirs |
| `Domain/Generator/Builder/CompiledPhpMethodBuilder` | Renders one wrapper method via reflection + token template |
| `Domain/Generator/Builder/CompiledPhpClassBuilder` | Wraps methods into a namespaced class |
| `Domain/DirectoryRemover/DirectoryRemover` | Wipes target dir before regen |
| `Domain/ReadModel/{FunctionToExport,Wrapper}` | Value objects |
| `PhelCallerTrait` | Mixed into every wrapper; `callPhel(ns, def, ...args)` |

## Key Constraints

- Only `^{:export true}` fns are exported.
- Export target directory is wiped and regenerated each run.
- Phel ns → PHP: hyphens become CamelCase (`my-lib` → `MyLib`); generated method names camelCase the fn name (`my-fn` → `myFn`).
- `CompiledPhpMethodBuilder` reflects the compiled fn class's `BOUND_TO` constant + `__invoke` signature; do not bypass — params/return type/ns come from there.
- `^{:php/attr [...]}` on an exported `defn` is threaded through `FunctionToExport` and rendered via `Phel\Shared\PhpAttributeRenderer` as PHP 8 attributes above the wrapper method (e.g. `#[\…\Route('/p')]`).
- `PhelCallerTrait` caches resolved definitions in a process-wide static keyed by `namespace::definitionName`, never invalidated. Safe for CLI / per-request FPM; a wrapper keeps calling the originally resolved definition if the runtime redefines it mid-process.

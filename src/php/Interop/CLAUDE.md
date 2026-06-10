# Interop Module

Generates PHP wrapper classes for Phel functions marked with `^{:export true}`.

## Public API (Facade)

- `generateExportCode(): list<Wrapper>`: orchestrates full pipeline (`ExportCodeGenerator`)
- `writeLocatedException` / `writeStackTrace`: delegate to Command

## Dependencies

Command (error/exception output), Build (namespace extraction, compilation, evaluation). `InteropConfig` exposes export dirs, namespace prefix, target directory.

## Key Constraints

- Only functions with `^{:export true}` metadata exported (`Domain/ExportFinder/FunctionsToExportFinder`)
- Phel namespaces to PHP: hyphens become CamelCase (my-lib -> MyLib)
- Generated classes use `PhelCallerTrait` (`callPhel(ns, def, ...args)`) for cached definition resolution
- Export directory wiped and regenerated each run
- `^{:php/attr [...]}` metadata on an exported `defn` is threaded through `FunctionToExport` and rendered (via `Phel\Shared\PhpAttributeRenderer` in `CompiledPhpMethodBuilder`) as PHP 8 attributes above the wrapper method (e.g. `#[\…\Route('/p')]`)

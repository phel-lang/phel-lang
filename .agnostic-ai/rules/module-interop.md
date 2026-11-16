---
name: module-interop
description: 'Load when editing src/php/Interop: PHP wrapper generation for Phel functions marked `^{:export true}`.'
scope: src/php/Interop
---

# Interop Module

Generates PHP wrapper classes for Phel functions marked `^{:export true}`, so PHP code can call them. `generateExportCode()` runs the pipeline (`Application/ExportCodeGenerator`); the other facade methods delegate error output to Command.

## Dependencies

- Provider: `CommandFacadeInterface` (error output) and `BuildFacadeInterface` (namespace extraction, compilation, evaluation).
- Config, the one non-facade edge: `InteropConfig` reads the `export` section of `PhelConfig` (`PhelExportConfig`): export directories, namespace prefix (default `PhelGenerated`), target directory (default `src/PhelGenerated`).

## Constraints

- The target directory is wiped (`DirectoryRemover`) and regenerated each run.
- Wrappers call `\Phel::assertBuiltWith(VersionResolver::current())` after the namespace declaration and before the class, so they need the exact generating Phel version. Run `phel export` after changing it.
- Naming: `my-lib` → namespace segment `MyLib`; `my-fn` → method `myFn`.
- `CompiledPhpMethodBuilder` reflects the compiled fn class's `BOUND_TO` constant and `__invoke` signature; params, return type and namespace come from there, so never bypass it. Native types from `:tag` are mirrored into the wrapper signature plus an `@param`/`@return` docblock. Fns without type info get a docblock-free `mixed` wrapper.
- A multi-arity fn reflects an untyped `__invoke($a0 = null, ...)` that dispatches on `func_num_args()` (`.agnostic-ai/rules/module-compiler-emitter.md`). Any optional non-variadic param makes the wrapper forward `...$args`: passing every param would always pick the widest arity. Its `@return` comes from the return `:tag` that `FunctionToExport` carries from definition metadata.
- `^{:php/attr [...]}` on an exported `defn` travels through `FunctionToExport` and is rendered above the wrapper method by `Phel\Shared\PhpAttributeRenderer`.
- `PhelCallerTrait` (mixed into every wrapper) caches resolved definitions in a process-wide static keyed by `namespace::definitionName`, never invalidated. Safe for CLI and per-request FPM; a redefinition mid-process is not seen.
- A wrapper resolves, never loads: the host must evaluate the namespace first. Otherwise the trait raises `ExportedDefinitionNotFoundException`, naming the source spelling of the namespace (`my-app.billing`, not `my_app.billing`) and whether the namespace is not loaded or the wrapper is stale.

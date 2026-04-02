# Interop Module

PHP-Phel interoperability: generates PHP wrapper classes for Phel functions marked with `^{:export true}`.

## Gacela Pattern

- **Facade**: `InteropFacade` implements `InteropFacadeInterface`
- **Factory**: `InteropFactory` extends `AbstractFactory<InteropConfig>`
- **Config**: `InteropConfig` — export dirs (`src/phel`), namespace prefix (`PhelGenerated`), target dir (`src/PhelGenerated`)
- **Provider**: `InteropProvider` — injects `CommandFacade` (`FACADE_COMMAND`) and `BuildFacade` (`FACADE_BUILD`)

## Public API (Facade)

- `generateExportCode(): array<Wrapper>` — orchestrates full export pipeline
- `writeLocatedException(OutputInterface, CompilerException): void` — delegates to Command
- `writeStackTrace(OutputInterface, Throwable): void` — delegates to Command

## Export Workflow

1. Remove old export directory
2. Discover Phel functions marked with `^{:export true}` metadata
3. Compile and evaluate all dependent namespaces
4. Generate PHP wrapper class per namespace (e.g. `my-lib` -> `PhelGenerated\MyLib`)
5. Write wrapper files to target directory

## Dependencies

- **Command** (`CommandFacade`) — error output
- **Build** (`BuildFacade`) — namespace extraction, compilation, evaluation

## Structure

```
Interop/
├── Application/        ExportCodeGenerator (main orchestrator)
├── Domain/
│   ├── DirectoryRemover/   Cleans target export dir
│   ├── ExportFinder/       FunctionsToExportFinder (scans for :export metadata)
│   ├── FileCreator/        Writes Wrapper objects to filesystem
│   ├── Generator/          WrapperGenerator, CompiledPhpClassBuilder, CompiledPhpMethodBuilder
│   └── ReadModel/          Wrapper, FunctionToExport
├── Infrastructure/     ExportCommand (CLI), FileSystemIo
├── PhelCallerTrait.php     Used in generated wrappers: callPhel(ns, def, ...args)
└── Gacela files
```

## Key Constraints

- Only functions with `^{:export true}` metadata are exported
- Phel namespaces are converted to PHP: hyphens to CamelCase (e.g. `my-lib` -> `MyLib`)
- Generated classes use `PhelCallerTrait` which caches resolved definitions
- The entire export directory is wiped and regenerated on each run

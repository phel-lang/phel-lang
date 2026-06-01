# Interop Module

Generates PHP wrapper classes for Phel functions marked with `^{:export true}`.

## Gacela Pattern

- **Facade**: `InteropFacade` implements `InteropFacadeInterface`
- **Factory**: `InteropFactory`
- **Config**: `InteropConfig` exposes export dirs, namespace prefix, target directory
- **Provider**: injects `CommandFacade` (FACADE_COMMAND), `BuildFacade` (FACADE_BUILD)

## Public API (Facade)

- `generateExportCode(): list<Wrapper>` orchestrates full pipeline
- `writeLocatedException(OutputInterface, CompilerException): void`
- `writeStackTrace(OutputInterface, Throwable): void`

## Dependencies

| Module | Usage |
|--------|-------|
| Command | Error/exception output via CommandFacade |
| Build | Namespace extraction, compilation, evaluation |

## Structure

```
Interop/
├── Application/           ExportCodeGenerator (orchestrator)
├── Domain/
│   ├── DirectoryRemover/  Removes stale export dir
│   ├── ExportFinder/      Scans for :export metadata (FunctionsToExportFinder)
│   ├── FileCreator/       Writes Wrapper to filesystem
│   ├── Generator/         WrapperGenerator, CompiledPhpClassBuilder, CompiledPhpMethodBuilder
│   └── ReadModel/         Wrapper, FunctionToExport
├── Infrastructure/        ExportCommand (CLI), FileSystemIo
├── PhelCallerTrait.php    In generated wrappers; callPhel(ns, def, ...args)
└── Gacela files
```

## Key Constraints

- Only functions with `^{:export true}` metadata exported
- Phel namespaces to PHP: hyphens become CamelCase (my-lib -> MyLib)
- Generated classes use PhelCallerTrait for cached definition resolution
- Export directory wiped and regenerated each run

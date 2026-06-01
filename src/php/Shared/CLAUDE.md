# Shared Module

Contract layer: facade interfaces, constants, and cross-module utilities; pure stateless helpers and exception registry.

## No Gacela Pattern

Pure contract module; no Facade, Factory, or DependencyProvider. Defines interfaces other modules implement.

## Facade Interfaces (8)

Located in `Facade/` subdirectory. Each module implements one:

| Interface | Module | Key Methods |
|-----------|--------|-------------|
| `CompilerFacadeInterface` | Compiler | `eval()`, `compile()`, `lexString()`, `analyze()`, `macroexpand()` |
| `BuildFacadeInterface` | Build | `compileFile()`, `compileProject()`, `getNamespaceFromFile()` |
| `RunFacadeInterface` | Run | `runNamespace()`, `eval()`, `structuredEval()`, `loadPhelNamespaces()` |
| `CommandFacadeInterface` | Command | `writeLocatedException()`, `getAllPhelDirectories()`, `getSourceDirectories()` |
| `ConsoleFacadeInterface` | Console | `getVersion()`, `runConsole()` |
| `FormatterFacadeInterface` | Formatter | `format()` |
| `InteropFacadeInterface` | Interop | `generateExportCode()` |
| `ApiFacadeInterface` | Api | `getPhelFunctions()`, `replComplete()`, `replCompleteWithTypes()` |

## Constants

- `CompilerConstants.PHEL_CORE_NAMESPACE = 'phel.core'`
- `BuildConstants.BUILD_MODE = '*build-mode*'`
- `ReplConstants.REPL_MODE = '*repl-mode*'`
- `CompileOptions.DEFAULT_SOURCE = 'string'`, `DEFAULT_STARTING_LINE = 1`, `DEFAULT_ENABLE_SOURCE_MAPS = true`, `DEFAULT_EMIT_ONLY = false`

## Exceptions

Located in `Exceptions/`:

- `CompilerException`: wraps `AbstractLocatedException` with `CodeSnippet`
- `AbstractLocatedException`: base for located errors with `SourceLocation` and `ErrorCode`
- `ErrorCode`: enum for PHEL001-PHEL310 error codes (analyzer, parser, reader, lexer)
- `FileException`: file/directory operations
- `CompiledCodeIsMalformedException`: wraps PHP `eval()` parse errors

## Parser Model

`Parser/ReadModel/CodeSnippet`: `SourceLocation` (start/end) and source string; pure data, no dependencies.

## Printer

Sub-module in `Printer/`: stateless, no I/O wiring. Entry point: `Printer::readable() | readableWithColor() | nonReadable()` then `print($form): string`. Consumers instantiate directly (no module boundary).

Strategy pattern via `TypePrinter/`: one class per Phel/PHP type; `WithColorTrait` for ANSI support.

## Utility Classes

- `ColorStyle`: ANSI colors (green, yellow, blue, red); factories: `withStyles()`, `noStyles()`
- `Munge`: namespace/symbol encoding; `encode()`, `encodePhpNs()`, `encodeRegistryKey()`, `decodeNs()`, `canonicalNs()`, `displayNs()`
- `ResourceUsageFormatter`: returns "Time: HH:MM:SS.mmm, Memory: X.XX MB" snapshot
- `PhelProjectDirectory`: manages `.phel/` directory; respects `PHEL_DIR` env var and `PhelConfig::setPhelDir()`
- `VersionFinder`: resolves project version from git state or official release tag
- `CompileOptions`: constants for source maps, emit-only mode, optimization levels

## Dependencies

None outward. Imports from other modules only in interface signatures (Compiler exceptions, Lang types, etc.).

## Used By

Every module imports its facade interface from here. Enables dependency inversion.

## Key Constraints

- Leaf dependency: contracts and utilities only, never business logic
- New modules must add `FacadeInterface` here
- Facade interfaces keep imports minimal
- Exceptions are cross-module: thrown and caught everywhere
- Utilities remain stateless: safe to instantiate without module context

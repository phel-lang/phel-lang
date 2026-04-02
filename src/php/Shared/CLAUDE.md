# Shared Module

Contract layer: facade interfaces and cross-cutting constants for inter-module communication.

## No Gacela Pattern

This is a **pure contract module** — no Facade, Factory, or DependencyProvider. It defines the interfaces that other modules implement.

## Facade Interfaces (8 total)

Located in `Facade/` subdirectory. Each module implements its corresponding interface:

| Interface | Implemented By | Key Methods |
|-----------|---------------|-------------|
| `CompilerFacadeInterface` | Compiler | `eval()`, `compile()`, `lexString()`, `analyze()`, `macroexpand()` |
| `BuildFacadeInterface` | Build | `compileFile()`, `compileProject()`, `getNamespaceFromFile()` |
| `RunFacadeInterface` | Run | `runNamespace()`, `eval()`, `structuredEval()`, `loadPhelNamespaces()` |
| `CommandFacadeInterface` | Command | `writeLocatedException()`, `getAllPhelDirectories()`, `getSourceDirectories()` |
| `ConsoleFacadeInterface` | Console | `getVersion()`, `runConsole()` |
| `FormatterFacadeInterface` | Formatter | `format()` |
| `InteropFacadeInterface` | Interop | `generateExportCode()` |
| `ApiFacadeInterface` | Api | `getPhelFunctions()`, `replComplete()`, `replCompleteWithTypes()` |

## Constants

- **CompilerConstants** — `PHEL_CORE_NAMESPACE = 'phel\core'`
- **BuildConstants** — `BUILD_MODE = '*build-mode*'`
- **ReplConstants** — `REPL_MODE = '*repl-mode*'`

## Utility Classes

- **ColorStyle** (implements `ColorStyleInterface`) — ANSI terminal colors: `green()`, `yellow()`, `blue()`, `red()`
  - Factory: `ColorStyle::withStyles()` / `ColorStyle::noStyles()`

## Dependencies

None outward. Imports types from other modules only in interface signatures (Compiler exceptions, Lang types, Build value objects, etc.).

## Used By

**Every module** depends on Shared for its facade interface contract. This enables dependency inversion — modules depend on interfaces, not implementations.

## Key Constraints

- This module must remain a **leaf dependency** — it defines contracts, never implements business logic
- Adding a new module requires adding its `FacadeInterface` here
- Facade interfaces import domain types (e.g. `CompilerException`, `TypeInterface`) — keep these imports minimal

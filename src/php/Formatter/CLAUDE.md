# Formatter Module

Code formatting for Phel source files: parses code into AST, applies formatting rules, writes back.

## Gacela Pattern

- **Facade**: `FormatterFacade` implements `FormatterFacadeInterface`
- **Factory**: `FormatterFactory` — creates `PathsFormatter`, `Formatter`, individual rules
- **Config**: `FormatterConfig` — `getFormatDirs()` (default: `['src/phel', 'tests/phel']`)
- **Provider**: `FormatterProvider` — injects `CompilerFacade` (`FACADE_COMPILER`) and `CommandFacade` (`FACADE_COMMAND`)

## Public API (Facade)

- `format(array $paths, OutputInterface $output): array` — format files, returns list of successfully formatted paths

## Formatting Rules (applied in order)

1. `RemoveSurroundingWhitespaceRule`
2. `UnindentRule`
3. `IndentRule` — uses specialized indenters:
   - `InnerIndenter` — for `def`, `defn`, `defmacro`, `deftest`, `fn`
   - `BlockIndenter` — for `if`, `do`, `let`, `try`, `case`, `cond`, etc.
   - `LineIndenter`, `ListIndenter`
4. `RemoveTrailingWhitespaceRule`

## Dependencies

- **Compiler** (`CompilerFacade`) — lexing and parsing Phel code
- **Command** (`CommandFacade`) — error reporting and CLI output

## Structure

```
Formatter/
├── Application/        Formatter, PathsFormatter, PhelPathFilter
├── Domain/             FormatterInterface, PathFilterInterface, RuleInterface, rules, indenters
├── Infrastructure/     FormatCommand (CLI), SystemFileIo
└── Gacela files        FormatterFacade, FormatterFactory, FormatterProvider, FormatterConfig
```

## Key Constraints

- Uses **Zipper pattern** (`ParseTreeZipper`, `AbstractZipper`) for tree navigation and transformation
- `PhelPathFilter` recursively discovers `.phel` files from given paths
- The formatter reads file -> lexes/parses via Compiler -> applies rules to AST -> writes back
- Adding new indenters requires updating `FormatterFactory.createFormatter()`

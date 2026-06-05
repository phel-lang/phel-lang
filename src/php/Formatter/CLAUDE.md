# Formatter Module

Parses Phel source into AST, applies formatting rules, writes back.

## Gacela Pattern

- **Facade**: `FormatterFacade` implements `FormatterFacadeInterface`
- **Factory**: `FormatterFactory` creates `PathsFormatter`, `Formatter`, rules and indenters
- **Config**: `FormatterConfig.getFormatDirs()` (default: `['src', 'tests']`)
- **Provider**: `FormatterProvider` injects `CompilerFacade` (`FACADE_COMPILER`) and `CommandFacade` (`FACADE_COMMAND`)

## Public API (Facade)

- `format(array $paths, OutputInterface $output, bool $dryRun = false): array` returns paths with changes
- `formatString(string $source, string $uri = '...'): string` formats code in memory

## Formatting Rules (in order)

1. `RemoveSurroundingWhitespaceRule`
2. `UnindentRule`
3. `RemoveConsecutiveBlankLinesRule` (collapses 2+ blank lines to one; cljfmt parity)
4. `IndentRule` with indenters:
   - `InnerIndenter`: `def`, `def-`, `defn`, `defn-`, `defmacro`, `defmacro-`, `deftest`, `fn`, `defstruct`, `defrecord`, `definterface`, `defexception`, `defenum`, `defprotocol`, `defmulti`, `defmethod`, `defonce`, `reify`
   - `BlockIndenter`: `catch`, `do`, `if`, `if-not`, `foreach`, `for`, `dofor`, `let`, `ns`, `loop`, `case`, `cond`, `condp`, `try`, `finally`, `when`, `when-not`, `when-let`, `when-some`, `if-let`, `if-some`, `binding`, `when-first`, `doseq`, `dotimes`, `letfn`, `with-redefs`, `with-bindings`, `extend-type`, `extend-protocol`, `with-output-buffer`, `delay`, `lazy-seq`
5. `AlignPairsRule`
6. `RemoveTrailingWhitespaceRule`

## Dependencies

- **Compiler**: `CompilerFacade` for lexing and parsing
- **Command**: `CommandFacadeInterface` for CLI output

## Structure

```
Formatter/
├── Application/         Formatter, PathsFormatter, PhelPathFilter
├── Domain/              FormatterInterface, PathFilterInterface, RuleInterface, IO/FileIoInterface, rules, indenters
├── Infrastructure/      Command/FormatCommand, IO/SystemFileIo
└── Gacela files         FormatterFacade, FormatterFactory, FormatterProvider, FormatterConfig
```

## Key Constraints

- Zipper pattern (`ParseTreeZipper`) for AST traversal and transformation
- `PhelPathFilter` recursively discovers `.phel` files
- Adding new indenters requires updating `FormatterFactory.createIndentRule()`

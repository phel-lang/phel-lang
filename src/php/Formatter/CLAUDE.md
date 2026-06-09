# Formatter Module

Parses Phel source into AST, applies formatting rules, writes back.

## Public API (Facade)

- `format(array $paths, OutputInterface $output, bool $dryRun = false): array`: returns paths with changes
- `formatString(string $source, string $uri = '...'): string`: formats code in memory

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

Compiler (lexing and parsing), Command (CLI output). `FormatterConfig.getFormatDirs()` defaults to `['src', 'tests']`.

## Key Constraints

- Zipper pattern (`ParseTreeZipper`) for AST traversal and transformation
- `PhelPathFilter` recursively discovers `.phel` files
- Adding new indenters requires updating `FormatterFactory.createIndentRule()`

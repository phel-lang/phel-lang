# Formatter Module

Code formatter for `phel format`: lex/parse Phel source to a parse tree, apply ordered rules via a zipper, write back.

## Public API (Facade)

| Method | Notes |
|--------|-------|
| `format(array $paths, OutputInterface $output, bool $dryRun = false): array` | Returns paths whose contents changed (or would under `$dryRun`). Discovers `.phel` files under `$paths`. |
| `formatString(string $source, string $uri = FormatterInterface::DEFAULT_SOURCE): string` | Formats in memory; no filesystem access. |

## Dependencies

- `FACADE_COMPILER` — lex + parse to parse tree.
- `FACADE_COMMAND` — CLI output.
- `FormatterConfig.getFormatDirs()` reads `PhelConfig::FORMAT_DIRS`, defaults to `['src', 'tests']`.

## Rules (applied in this order)

Wired in `FormatterFactory::createFormatter()`:

1. `RemoveSurroundingWhitespaceRule`
2. `UnindentRule`
3. `RemoveConsecutiveBlankLinesRule` — collapses 2+ blank lines to one (cljfmt parity)
4. `IndentRule` (indenters below)
5. `AlignPairsRule`
6. `RemoveTrailingWhitespaceRule`

## Indenters

Symbol lists live as `FormatterFactory` constants; `createIndentRule()` instantiates one indenter per symbol.

- `InnerIndenter` (const `INNER_INDENT_SYMBOLS`): body indented 2 spaces under head line. `def`, `def-`, `defn`, `defn-`, `defmacro`, `defmacro-`, `deftest`, `fn`, `defstruct`, `defrecord`, `definterface`, `defexception`, `defenum`, `defprotocol`, `defmulti`, `defmethod`, `defonce`, `reify`.
- `BlockIndenter` (const `BLOCK_INDENT_SYMBOLS`, symbol → leading-arg count before body):
  - `0`: `do`, `cond`, `try`, `finally`, `with-output-buffer`, `delay`, `lazy-seq`
  - `1`: `if`, `if-not`, `foreach`, `for`, `dofor`, `let`, `ns`, `loop`, `case`, `when`, `when-not`, `when-let`, `when-some`, `if-let`, `if-some`, `binding`, `when-first`, `doseq`, `dotimes`, `letfn`, `with-redefs`, `with-bindings`, `with-open`, `extend-type`, `extend-protocol`
  - `2`: `catch`, `condp`

## Structure

| Path | Role |
|------|------|
| `Application/Formatter.php` | Runs the rule pipeline over one source string |
| `Application/PathsFormatter.php` | Discovers files, formats each, reports changes |
| `Application/PhelPathFilter.php` | Recursively finds `.phel` files (impl of `PathFilterInterface`) |
| `Domain/Rules/` | Rule classes + `IndentRule` |
| `Domain/Rules/Indenter/` | `BlockIndenter`, `InnerIndenter`, `LineIndenter`, `ListIndenter` |
| `Domain/Rules/Pair/PairAligner.php` | Backing logic for `AlignPairsRule` |
| `Domain/Rules/Zipper/` | `ParseTreeZipper` (AST traversal/transform), `AbstractZipper` |
| `Infrastructure/IO/SystemFileIo.php` | `FileIoInterface` impl |
| `Infrastructure/Command/FormatCommand.php` | `phel format` CLI command |

## Key Constraints

- Rules traverse/transform the parse tree via the zipper (`ParseTreeZipper`); add new rules as `RuleInterface` impls and wire them into `createFormatter()` in pipeline order.
- Adding/changing an indenter means editing the `INNER_INDENT_SYMBOLS` / `BLOCK_INDENT_SYMBOLS` constants in `FormatterFactory` — `createIndentRule()` builds them automatically.

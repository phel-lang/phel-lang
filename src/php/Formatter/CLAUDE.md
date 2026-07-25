# Formatter Module

Code formatter for `phel format`: lex/parse Phel source to a parse tree, apply ordered rules via a zipper, write back.

## Public API (Facade)

| Method | Notes |
|--------|-------|
| `format(array $paths, OutputInterface $output, bool $dryRun = false): FormatResult` | Discovers `.phel` files under `$paths` and formats each one. |
| `formatString(string $source, string $uri = FormatterInterface::DEFAULT_SOURCE): string` | Formats in memory; no filesystem access. |

### `FormatResult` contract

`Phel\Shared\Formatter\FormatResult` (`final readonly`, lives in Shared so the facade interface stays leaf-safe):

| Accessor | Meaning |
|----------|---------|
| `changedPaths(): list<string>` | Contents changed, or would change under `$dryRun` |
| `failedPaths(): list<string>` | Could not be formatted: unreadable path, or source that fails to lex/parse |
| `hasChanges()` / `hasFailures()` | Emptiness predicates for the two lists |

Each path lands in at most one bucket; an already-formatted file appears in neither. A failed path is reported on `$output` (located exception or stack trace) and skipped, so one broken file never aborts the batch, but it **must** still make the caller exit non-zero. `FormatCommand` returns `FAILURE` when `hasFailures()`, and additionally under `--dry-run` when `hasChanges()`.

## Dependencies

- `FACADE_COMPILER` — lex + parse to parse tree (`lexString`, `parseAll`). Injected as `CompilerFacadeInterface` via `FormatterFactory::getCompilerFacade()`.
- `FACADE_COMMAND` — CLI output, as `CommandFacadeInterface`.
- Config — `FormatterConfig.getFormatDirs()` reads `PhelConfig::FORMAT_DIRS`, defaults to `['src', 'tests']`.

Both getters return the Shared contract, never a concrete facade, and `SatelliteFactoryFacadeInjectionTest` pins the return types. The residual `Formatter -> Compiler` imports are exception types named in `@throws` (`LexerValueException`, `AbstractParserException`), not behaviour.

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
  - `0`: `do`, `cond`, `try`, `finally`, `with-output-buffer`, `delay`, `lazy-seq`, `with-isolated-stats`
  - `1`: `if`, `if-not`, `foreach`, `for`, `dofor`, `let`, `ns`, `loop`, `case`, `when`, `when-not`, `when-let`, `when-some`, `if-let`, `if-some`, `binding`, `when-first`, `doseq`, `dotimes`, `letfn`, `with-redefs`, `with-bindings`, `with-open`, `with-isolated-reporters`, `extend-type`, `extend-protocol`
  - `2`: `catch`, `condp`

## Structure

| Path | Role |
|------|------|
| `Application/Formatter.php` | Runs the rule pipeline over one source string |
| `Application/PathsFormatter.php` | Discovers files, formats each, reports changes |
| `Application/PhelPathFilter.php` | Recursively finds `.phel` files (impl of `PathFilterInterface`) |
| `Domain/Rules/` | Rule classes + `IndentRule` |
| `Domain/Rules/Indenter/` | `BlockIndenter`, `InnerIndenter`, `LineIndenter`, `ListIndenter`; `FormSymbolMatcherTrait` reads a location's head symbol and matches it against the indenter's symbol |
| `Domain/Rules/Pair/PairAligner.php` | Backing logic for `AlignPairsRule` |
| `Domain/Rules/Zipper/` | `ParseTreeZipper` (AST traversal/transform), `AbstractZipper` |
| `Infrastructure/IO/SystemFileIo.php` | `ValidatedFileIoInterface` impl |
| `Infrastructure/Command/FormatCommand.php` | `phel format` CLI command |

## Key Constraints

- Rules traverse/transform the parse tree via the zipper (`ParseTreeZipper`); add new rules as `RuleInterface` impls and wire them into `createFormatter()` in pipeline order.
- Adding/changing an indenter means editing the `INNER_INDENT_SYMBOLS` / `BLOCK_INDENT_SYMBOLS` constants in `FormatterFactory` — `createIndentRule()` builds them automatically.

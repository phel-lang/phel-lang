# Formatter Module

Code formatter for `phel format`: lex/parse Phel source to a parse tree, apply ordered rules via a zipper, write back.

## Public API (Facade)

| Method | Notes |
|--------|-------|
| `format(array $paths, OutputInterface $output, bool $dryRun = false, array $exclude = []): FormatResult` | Discovers `.phel` files under `$paths` and formats each one; a file matching an `$exclude` glob (`Domain/ExcludePatterns`: `fnmatch` against the path as found and relative to cwd, `*` spans directories) is skipped and lands in neither result list, named under `-v`. |
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

- `CompilerFacadeInterface::class` — lex + parse to parse tree (`lexString`, `parseAll`).
- `CommandFacadeInterface::class` — CLI output.
- Config — `FormatterConfig.getFormatDirs()` reads `PhelConfig::FORMAT_DIRS`, defaults to `['src', 'tests']`; `getFormatExclude()` reads `PhelConfig::FORMAT_EXCLUDE` (default none), which `FormatCommand` unions with `--exclude`.

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

- `InnerIndenter` (const `INNER_INDENT_SYMBOLS`): body indented 2 spaces under head line. `def`, `def-`, `defn`, `defn-`, `defmacro`, `defmacro-`, `deftest`, `defbench`, `fn`, `defstruct`, `defrecord`, `definterface`, `defexception`, `defenum`, `defprotocol`, `defmulti`, `defmethod`, `defonce`, `reify`.
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
| `Domain/ExcludePatterns.php` | The globs `phel format` skips (`--exclude` + `format-exclude`) |
| `Domain/Rules/` | Rule classes + `IndentRule` |
| `Domain/Rules/Indenter/` | `BlockIndenter`, `InnerIndenter`, `LineIndenter`, `ListIndenter`; `FormSymbolMatcherTrait` reads a location's head symbol and matches it against the indenter's symbol |
| `Domain/Rules/Pair/PairAligner.php` | Backing logic for `AlignPairsRule` |
| `Domain/Rules/Zipper/` | `ParseTreeZipper` (AST traversal/transform), `AbstractZipper` |
| `Infrastructure/IO/SystemFileIo.php` | `ValidatedFileIoInterface` impl |
| `Infrastructure/Command/FormatCommand.php` | `phel format` CLI command |

## Key Constraints

- Rules traverse/transform the parse tree via the zipper (`ParseTreeZipper`); add new rules as `RuleInterface` impls and wire them into `createFormatter()` in pipeline order.
- Adding/changing an indenter means editing the `INNER_INDENT_SYMBOLS` / `BLOCK_INDENT_SYMBOLS` constants in `FormatterFactory` — `createIndentRule()` builds them automatically.
- `AbstractZipper` holds a location as the parent's whole child list plus an index, so `left`/`right`/`leftMost`/`rightMost`/`next` are O(1) and a walk over a literal of n elements is O(n); the two-halves representation made it O(n^2) and a 16 000-element literal took 28 s (#3218). `insertLeft`/`insertRight`/`remove` still copy the sibling list (PHP arrays), so a rule that edits once per line inside one huge literal is O(elements x lines): fine for real files, but do not add per-element edits to a rule walk.

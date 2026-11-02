---
name: module-formatter
description: 'Load when changing src/php/Formatter: `phel format`, formatting rules, indenters, exclude globs, FormatResult.'
scope: src/php/Formatter
---

# Formatter Module

`phel format`: lex/parse Phel source to a parse tree, apply ordered rules via a zipper, write back.

## Facade

| Method | Notes |
|--------|-------|
| `format(array $paths, OutputInterface $output, bool $dryRun = false, array $exclude = []): FormatResult` | Formats each `.phel` file under `$paths`. A file matching an `$exclude` glob (`Domain/ExcludePatterns`: `fnmatch` against the path as found and relative to cwd, `*` spans directories) is skipped, lands in neither result list, and is named under `-v`. |
| `formatString(string $source, string $uri = CompilerConstants::DEFAULT_SOURCE): string` | In memory; no filesystem access. |

### `FormatResult`

`Phel\Shared\Formatter\FormatResult` (`final readonly`, in Shared so the facade interface stays leaf-safe):

| Accessor | Meaning |
|----------|---------|
| `changedPaths(): list<string>` | Contents changed, or would change under `$dryRun` |
| `failedPaths(): list<string>` | Unreadable path, or source that fails to lex/parse |
| `hasChanges()` / `hasFailures()` | Emptiness predicates |

Each path lands in at most one bucket; an already-formatted file in neither. A failed path is reported on `$output` (located exception or stack trace) and skipped, so one broken file never aborts the batch, but the caller **must** still exit non-zero. `FormatCommand` returns `FAILURE` on `hasFailures()`, and under `--dry-run` also on `hasChanges()`.

## Dependencies

- `CompilerFacadeInterface::class`: `lexString`, `parseAll`.
- `CommandFacadeInterface::class`: CLI output.
- `FormatterConfig.getFormatDirs()` reads `PhelConfig::FORMAT_DIRS` (default `['src', 'tests']`). `getFormatExclude()` reads `PhelConfig::FORMAT_EXCLUDE` (default none); `FormatCommand` unions it with `--exclude`.

Both getters return the Shared contract, never a concrete facade (`SatelliteFactoryFacadeInjectionTest` pins the return types). The remaining `Formatter -> Compiler` imports are exception types in `@throws` (`LexerValueException`, `AbstractParserException`).

## Rules (pipeline order, wired in `FormatterFactory::createFormatter()`)

1. `RemoveSurroundingWhitespaceRule`
2. `UnindentRule`
3. `RemoveConsecutiveBlankLinesRule`: 2+ blank lines to one (cljfmt parity)
4. `IndentRule` (indenters below)
5. `AlignPairsRule`
6. `RemoveTrailingWhitespaceRule`

## Indenters

Symbol lists are `FormatterFactory` constants; `createIndentRule()` builds one indenter per symbol.

- `InnerIndenter` (`INNER_INDENT_SYMBOLS`): body indented 2 spaces under the head line. `def`, `def-`, `defn`, `defn-`, `defmacro`, `defmacro-`, `deftest`, `defbench`, `fn`, `defstruct`, `defrecord`, `definterface`, `defexception`, `defenum`, `defprotocol`, `defmulti`, `defmethod`, `defonce`, `reify`.
- `BlockIndenter` (`BLOCK_INDENT_SYMBOLS`, symbol to leading-arg count before body):
  - `0`: `do`, `cond`, `try`, `finally`, `with-output-buffer`, `delay`, `lazy-seq`, `with-isolated-stats`
  - `1`: `if`, `if-not`, `foreach`, `for`, `dofor`, `let`, `ns`, `loop`, `case`, `when`, `when-not`, `when-let`, `when-some`, `if-let`, `if-some`, `binding`, `when-first`, `doseq`, `dotimes`, `letfn`, `with-redefs`, `with-bindings`, `with-open`, `with-isolated-reporters`, `extend-type`, `extend-protocol`
  - `2`: `catch`, `condp`

## Structure

| Path | Role |
|------|------|
| `Application/Formatter.php` | Runs the rule pipeline over one source string |
| `Application/PathsFormatter.php` | Discovers files, formats each, reports changes |
| `Application/PhelPathFilter.php` | Recursively finds `.phel` files (`PathFilterInterface`) |
| `Domain/ExcludePatterns.php` | Globs `phel format` skips (`--exclude` + `format-exclude`) |
| `Domain/Rules/` | Rule classes + `IndentRule` |
| `Domain/Rules/Indenter/` | `BlockIndenter`, `InnerIndenter`, `LineIndenter`, `ListIndenter`; `FormSymbolMatcherTrait` matches a location's head symbol against the indenter's symbol |
| `Domain/Rules/Pair/PairAligner.php` | Logic for `AlignPairsRule` |
| `Domain/Rules/Zipper/` | `ParseTreeZipper`, `AbstractZipper` |
| `Infrastructure/IO/SystemFileIo.php` | `ValidatedFileIoInterface` impl |
| `Infrastructure/Command/FormatCommand.php` | `phel format` |

## Key Constraints

- Rules transform the parse tree via `ParseTreeZipper`. Add a rule as a `RuleInterface` impl and wire it into `createFormatter()` in pipeline order.
- Change an indenter by editing `INNER_INDENT_SYMBOLS` / `BLOCK_INDENT_SYMBOLS` in `FormatterFactory`.
- `AbstractZipper` holds a location as the parent's whole child list plus an index, so `left`/`right`/`leftMost`/`rightMost`/`next` are O(1) and a walk over n elements is O(n). The two-halves representation was O(n^2): a 16 000-element literal took 28 s (#3218). `insertLeft`/`insertRight`/`remove` still copy the sibling list, so a rule editing once per line inside one huge literal is O(elements x lines). Add no per-element edits to a rule walk.

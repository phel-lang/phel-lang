---
name: module-formatter
description: 'Load when changing src/php/Formatter: `phel format`, formatting rules, indenters, exclude globs, FormatResult.'
scope: src/php/Formatter
---

# Formatter Module

`phel format`: lex and parse Phel source to a parse tree, apply ordered rules through a zipper, write back.

## Facade

- `format($paths, $output, $dryRun, $exclude): FormatResult` formats each `.phel` file under `$paths`. A file matching an exclude glob (`Domain/ExcludePatterns`: `fnmatch` against the path as found and relative to cwd, `*` spans directories) is skipped, lands in neither result list, and is named under `-v`.
- `formatString($source, $uri)`: in memory, no filesystem access.
- `FormatResult` lives in Shared so the facade interface stays leaf-safe. Each path lands in at most one of `changedPaths` / `failedPaths`. A failed path (unreadable, or fails to lex or parse) is reported on `$output` and skipped, so one broken file never aborts the batch, but the caller **must** still exit non-zero. `FormatCommand` returns `FAILURE` on `hasFailures()`, and under `--dry-run` also on `hasChanges()`.

## Dependencies

- `CompilerFacadeInterface`: `lexString`, `parseAll`. The remaining direct Compiler imports are exception types in `@throws` (`LexerValueException`, `AbstractParserException`).
- `CommandFacadeInterface`: CLI output.
- Config: `PhelConfig::FORMAT_DIRS` (default `['src', 'tests']`) and `FORMAT_EXCLUDE` (default none; `FormatCommand` unions it with `--exclude`).

## Key Constraints

- Rule pipeline order is set in `FormatterFactory::createFormatter()`: surrounding whitespace, unindent, consecutive blank lines (2+ to one, cljfmt parity), indent, align pairs, trailing whitespace. A new rule implements `RuleInterface`, transforms via `ParseTreeZipper`, and is wired there in order.
- Indentation is driven by `INNER_INDENT_SYMBOLS` (body 2 spaces under the head) and `BLOCK_INDENT_SYMBOLS` (symbol to leading-arg count before the body) in `FormatterFactory`. Change an indent by editing those constants.
- `AbstractZipper` holds a location as the parent's whole child list plus an index, so moves are O(1) and a walk over n elements is O(n). The old two-halves representation was O(n^2): a 16 000-element literal took 28 s (#3218). `insertLeft`/`insertRight`/`remove` still copy the sibling list, so a rule editing once per line inside one huge literal is O(elements x lines). Add no per-element edits to a rule walk.

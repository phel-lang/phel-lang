---
name: module-balance
description: 'Load when changing src/php/Balance: `phel balance`, delimiter detection and repair.'
scope: src/php/Balance
---

# Balance Module

Reports, and on request appends, the `()`, `[]` and `{}` a Phel file is missing. The only code that **writes a file it could not parse**; it fits neither `Formatter` (writes from a parse tree) nor `Lint` (never rewrites).

## Facade (internal CLI plumbing, outside public semver, ADR 0021)

| Method | Returns |
|--------|---------|
| `balance(list<string> $paths, bool $fix = false)` | `BalanceResult`; throws `BalanceSourceException` when a listed directory cannot be walked |

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Compiler | `CompilerFacadeInterface` | `lexString()` only |
| Command | `CommandFacadeInterface` | default source dirs for bare `phel balance` |

No module imports `Balance`, so no cycle beyond the four ADR 0004 accepts. No `Phel\Shared\Facade` contract; `Console` only wraps its command.

## CLI

`./bin/phel balance [paths]... [--fix]`. Exit `0` balanced or fully repaired, `1` imbalance remains, `2` invocation error (missing path, unwalkable dir).

Detection is the default; writing is opt-in. The caller is an agent post-write hook, and a wrong guess is worse than the compile error it replaces. A fully repaired `--fix` run exits `0` so the hook passes.

## Scan on tokens, never on bytes

`DelimiterScanner` consumes `CompilerFacadeInterface::lexString()`, which tokenizes mismatched files and neutralizes what a byte counter gets wrong:

| Construct | Token |
|---|---|
| `\(` `\)` `\[` `\]` `\{` `\}` | one `T_CHAR` each |
| `"a ( b"`, `"\" ("` | one `T_STRING` |
| `; ) ) )` | one `T_COMMENT`, trailing newline included |
| `#"^(a\|b)$"` | one `T_REGEX` |
| `#(` `#{` `#?(` `#?@(` | one token each, swallowing its opener |

Opener and closer text are not mirror images (`#?(` closes with `)`, `#{` with `}`): use the `CLOSER_TEXT_FOR_OPENER` lookup.

Do **not** reuse `Compiler`'s `ParenthesesChecker`: it returns `true` for `(foo))` on purpose so the REPL stops buffering (three REPL callers depend on it), and it has no positions.

## What it refuses to repair

Repair only **appends** missing closers, innermost first. These cases are reported and left byte-identical (several plausible fixes each):

- **Surplus or mismatched closer.** `(foo]` could mean `(foo)` or `[foo]`.
- **Unterminated string.** The atom rule swallows an unclosed `"`: in `(println "hi) (there` the `)` meant as string content lexes as a closer, so the imbalance is a phantom. Detect it by a `T_ATOM` starting with `"`, which no valid atom does.
- **Source that will not lex.** An unterminated `#"regex"`, a bare `#`, the removed `#| |#` comment raise `LexerValueException`. Handle lex failure, not only parse failure.
- **A new top-level form after the unclosed level.** The naive repair *compiles*: a missing `)` in `f` before `(defn g ...)` makes appending nest `g` inside `f`, a different program that lints clean. Mark a form (from token positions, only to refuse, never to place a closer) by:
  - an opener at **column 0** (as `phel format` enforces);
  - an opener whose **reader prefix** is at column 0 (`'(defn g ...)`);
  - an opener at **any column** followed by a definition head (`ns`, `def`, `defn`, `defmacro`, `deftest`, ...), since mid-edit files are unformatted. An ordinary indented call is not marked: the unclosed level is still collecting it.

  The message names the line the closer belongs before.
- **A trailing reader prefix.** `'`, `` ` ``, `~`, `~@`, `^`, `@`, `#'`, `#_` and tagged literals read the next form, so an appended closer becomes it (`#_)` does not parse). Also a lone trailing `\`: an appended `)` becomes its character, `\)` closes nothing, and the run would report a repair.

## Where the closers land

End of the last non-blank line. After a trailing `;` comment they go on their own line (`BalanceReport::endsInLineComment`).

`isBalanced()` and `isRepairable()` are not opposites (a file can be unbalanced and unrepairable): never infer one from the other.

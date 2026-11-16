---
name: module-balance
description: 'Load when changing src/php/Balance: `phel balance`, delimiter detection and repair.'
scope: src/php/Balance
---

# Balance Module

`phel balance [paths]... [--fix]` reports, and on request appends, the `()`, `[]` and `{}` a Phel file is missing. The only code that **writes a file it could not parse**, so it fits neither Formatter (writes from a parse tree) nor Lint (never rewrites). Internal CLI plumbing, outside public semver (ADR 0021).

## Facade and dependencies

- `balance($paths, $fix)` returns `BalanceResult`; throws `BalanceSourceException` when a listed directory cannot be walked.
- `CompilerFacadeInterface`: `lexString()` only. `CommandFacadeInterface`: default source dirs for a bare `phel balance`.

## CLI contract

Exit `0` balanced or fully repaired, `1` imbalance remains, `2` invocation error (missing path, unwalkable dir). Detection is the default, writing opt-in: the caller is an agent post-write hook, and a wrong guess is worse than the compile error it replaces. A fully repaired `--fix` run exits `0` so the hook passes.

## Scan tokens, never bytes

`DelimiterScanner` consumes `lexString()`, which tokenizes mismatched files and neutralizes what a byte counter gets wrong:

| Construct | Token |
|---|---|
| `\(` `\)` `\[` `\]` `\{` `\}` | one `T_CHAR` each |
| `"a ( b"`, `"\" ("` | one `T_STRING` |
| `; ) ) )` | one `T_COMMENT`, trailing newline included |
| `#"^(a\|b)$"` | one `T_REGEX` |
| `#(` `#{` `#?(` `#?@(` | one token each, swallowing its opener |

Opener and closer text are not mirror images (`#?(` closes with `)`): use `CLOSER_TEXT_FOR_OPENER`.

Do **not** reuse Compiler's `ParenthesesChecker`: it returns `true` for `(foo))` on purpose so the REPL stops buffering, and it has no positions.

## What it refuses to repair

Repair only **appends** missing closers, innermost first. These cases are reported and left byte-identical (each has several plausible fixes):

- **Surplus or mismatched closer.** `(foo]` could mean `(foo)` or `[foo]`.
- **Unterminated string.** The atom rule swallows an unclosed `"`: in `(println "hi) (there` the `)` meant as string content lexes as a closer, so the imbalance is a phantom. Detect it by a `T_ATOM` starting with `"`, which no valid atom does.
- **Source that will not lex.** An unterminated `#"regex"` or a bare `#` (including the removed `#| |#` comment) raises `LexerValueException`. Handle lex failure, not only parse failure.
- **A new top-level form after the unclosed level.** The naive repair *compiles*: a missing `)` in `f` before `(defn g ...)` would nest `g` inside `f`, a different program that lints clean. A form start (used only to refuse, never to place a closer) is:
  - an opener at **column 0** (as `phel format` enforces);
  - an opener whose **reader prefix** is at column 0 (`'(defn g ...)`);
  - an opener at **any column** followed by a definition head (`ns`, `def`, `defn`, `defmacro`, `deftest`, ...), since mid-edit files are unformatted. An ordinary indented call is not a form start.

  The message names the line the closer belongs before.
- **A reader prefix still short of a form.** `'`, `` ` ``, `~`, `~@`, `@`, `#'`, `#_` and tagged literals read one form, `^` reads two, so an appended closer would become the missing form (`(list ^:foo)` does not parse). The scanner counts owed forms per level and replays the appended closers. A finished `#_` and a closed `#?(` (which may read as nothing) give no form to the prefix before them. Also a lone trailing `\`: an appended `)` becomes its character.

## Where closers land

End of the last non-blank line; after a trailing `;` comment, on their own line (`BalanceReport::endsInLineComment`).

`isBalanced()` and `isRepairable()` are not opposites (a file can be unbalanced and unrepairable): never infer one from the other.

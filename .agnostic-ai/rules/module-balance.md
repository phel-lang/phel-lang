---
name: module-balance
description: 'Balance module: delimiter repair for `phel balance`.'
scope: src/php/Balance
---

# Balance Module

Reports, and on request appends, the `()`, `[]` and `{}` a Phel file is missing.

The only code that **writes a file it could not parse**, so it fits neither
`Formatter` (writes from a parse tree) nor `Lint` (never rewrites).

## Internal tooling API (Facade)

| Method | Returns |
|--------|---------|
| `balance(list<string> $paths, bool $fix = false)` | `BalanceResult`; throws `BalanceSourceException` when a listed directory cannot be walked |

Internal CLI plumbing, outside the public PHP semver surface (ADR 0021).

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Compiler | `CompilerFacadeInterface` | `lexString()` only |
| Command | `CommandFacadeInterface` | default source dirs for bare `phel balance` |

No module imports `Balance`, so no cycle beyond the four ADR 0004 accepts. No
`Phel\Shared\Facade` contract; `Console` only wraps its command.

## CLI

`./bin/phel balance [paths]... [--fix]`. Exit `0` balanced or fully repaired,
`1` imbalance remains, `2` invocation error (missing path, unwalkable dir).

**Detection is the default.** Writing is opt-in: the intended caller is an agent
post-write hook, and a wrong guess is worse than the compile error it replaces.
A fully repaired `--fix` run exits `0` so the hook passes.

## Scan on tokens, never on bytes

`DelimiterScanner` consumes `CompilerFacadeInterface::lexString()`. The lexer
tokenizes mismatched files and neutralizes what a byte counter gets wrong:

| Construct | Token |
|---|---|
| `\(` `\)` `\[` `\]` `\{` `\}` | one `T_CHAR` each (where byte counters die) |
| `"a ( b"`, `"\" ("` | one `T_STRING` |
| `; ) ) )` | one `T_COMMENT`, trailing newline included |
| `#"^(a\|b)$"` | one `T_REGEX` |
| `#(` `#{` `#?(` `#?@(` | one token each, swallowing its opener |

So opener and closer text are not mirror images (`#?(` closes with `)`, `#{`
with `}`), hence the `CLOSER_TEXT_FOR_OPENER` lookup.

Do **not** reuse `Compiler`'s `ParenthesesChecker`: it returns `true` for
`(foo))` on purpose so the REPL stops buffering (three REPL callers depend on
it), and it has no positions.

## What it refuses to repair

Repair only **appends** missing closers, innermost first. Five cases are
reported and left byte-identical, since each has several plausible fixes.

- **Surplus or mismatched closer.** `(foo]` could mean `(foo)` or `[foo]`.
- **Unterminated string.** The atom rule swallows an unclosed `"`, so in
  `(println "hi) (there` the `)` meant as string content lexes as a closer; the
  imbalance is a phantom and appending writes a differently broken file.
  Detected by a `T_ATOM` starting with `"`, which no valid atom does.
- **Source that will not lex.** An unterminated `#"regex"`, a bare `#`, the
  removed `#| |#` comment raise `LexerValueException`. Handle lex failure, not
  only parse failure.
- **A new top-level form after the unclosed level.** The dangerous one: the
  naive repair *compiles*. A missing `)` in `f` before `(defn g ...)` makes
  appending nest `g` inside `f` as a closure: different program, reported as
  success, lints clean. A form is marked (from token positions, only to refuse,
  never to place a closer) by:
  - an opener at **column 0** (as `phel format` enforces);
  - an opener whose **reader prefix** is at column 0 (`'(defn g ...)`);
  - an opener at **any column** followed by a definition head (`ns`, `def`,
    `defn`, `defmacro`, `deftest`, ...) (mid-edit files are unformatted). An ordinary indented call is not marked:
    the unclosed level is still collecting it.

  The message names the line the closer belongs before.
- **A trailing reader prefix.** `'`, `` ` ``, `~`, `~@`, `^`, `@`, `#'`, `#_`
  and tagged literals read the next form, so an appended closer becomes it
  (`#_)` does not parse). Also a lone trailing `\`: with no next character it
  falls to the atom rule, an appended `)` becomes its character, and `\)`
  closes nothing while the run reports a repair.

## Where the closers land

End of the last non-blank line. After a trailing `;` comment they go on their
own line, since inside the comment they would be text
(`BalanceReport::endsInLineComment`).

`isBalanced()` and `isRepairable()` are not opposites: a file can be both
unbalanced and unrepairable, so never infer one from the other.

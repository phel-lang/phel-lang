# Balance Module

Delimiter repair: reports, and on request appends, the `()`, `[]` and `{}` a
Phel source file is missing.

It is the only thing in the codebase that **writes a file it could not parse**.
That is why it is its own module: `Formatter` can write but only from a parse
tree, and `Lint` can read broken input but never rewrites it. `Balance` is the
intersection and belongs to neither.

## Public API (Facade)

| Method | Returns |
|--------|---------|
| `balance(list<string> $paths, bool $fix = false)` | `BalanceResult`; throws `BalanceSourceException` when a listed directory cannot be walked |

## Dependencies

| Facade | Injected as | Used for |
|--------|-------------|----------|
| Compiler | `CompilerFacadeInterface` | `lexString()` only |
| Command | `CommandFacadeInterface` | default source directories for a bare `phel balance` |

No module imports `Balance`, so it adds no cycle to the four ADR 0004 accepts.
It has no `Phel\Shared\Facade` contract, matching `Lint`, `Lsp`, `Nrepl`,
`Profile` and `Watch`: nothing injects it, `Console` only wraps its command.

## CLI

`./bin/phel balance [paths]... [--fix]`

Exit codes: `0` all balanced, or `--fix` repaired everything it found; `1` an
imbalance remains; `2` invocation error (no readable path, unwalkable dir).

**Detection is the default.** Writing is opt-in because the intended caller is
an agent post-write hook, and a hook that silently guesses wrong is worse than
the compile error it replaces. A `--fix` run that repaired everything exits `0`
so it does not fail the hook.

## Scan on tokens, never on bytes

`DelimiterScanner` consumes `CompilerFacadeInterface::lexString()`. The lexer
never parses, so it tokenizes a file whose delimiters do not match, and it
neutralizes every construct a character counter gets wrong:

| Construct | Token |
|---|---|
| `\(` `\)` `\[` `\]` `\{` `\}` | one `T_CHAR` each. This is where a byte counter dies |
| `"a ( b"`, `"\" ("` | one `T_STRING` |
| `; ) ) )` | one `T_COMMENT`, trailing newline included |
| `#"^(a\|b)$"` | one `T_REGEX` |
| `#(` `#{` `#?(` `#?@(` | one token each, swallowing its own opener |

Because the hash openers swallow their `(`, opener text and closer text are not
mirror images: `#?(` closes with a plain `)`, `#{` with `}`. `CLOSER_TEXT_FOR_OPENER`
is a lookup for exactly that reason.

Do **not** reuse `Compiler`'s `ParenthesesChecker`. It returns `true` for `(foo))`
on purpose, so the REPL stops buffering, and its three REPL callers depend on
that. It also carries no positions.

## What it refuses to repair

Repair only ever **appends** missing closers, innermost level first. Five cases
are reported and left byte-identical, because each has more than one plausible
fix. Refusing is the cheap error: it costs a manual fix, where a wrong repair
costs a rewritten program.

- **Surplus or mismatched closer.** `(foo]` could have meant `(foo)` or `[foo]`.
- **Unterminated string literal.** The atom rule swallows an unclosed `"`, so in
  `(println "hi) (there` the `)` the author meant as string content lexes as a
  real closer. The imbalance the stack reports is a phantom, and appending to it
  writes a differently broken file. Detected by a `T_ATOM` starting with `"`,
  which no valid Phel atom does.
- **Source that will not lex.** An unterminated `#"regex"`, a bare `#` and the
  removed `#| |#` block comment raise `LexerValueException` rather than lexing to
  something countable. Handle lex failure, not only parse failure.
- **A new top-level form after the unclosed level.** The dangerous one, because
  the naive repair *compiles*. Given a missing `)` in `f` followed by a
  `(defn g ...)`, appending at the end nests `g` inside `f` as a closure: valid
  code, different program, reported as a success, and it lints clean. Three
  readings mark such a form, all off token positions and all used only to
  refuse, never to place a closer:
  - an opener at **column 0**, the convention `phel format` enforces;
  - an opener whose **reader prefix** started at column 0, so `'(defn g ...)`
    counts even though its `(` sits at column 1;
  - an opener at **any column** followed by a definition head (`ns`, `def`,
    `defn`, `defmacro`, `deftest`, ...), because a file missing a closer is
    usually mid-edit and no longer formatted. An ordinary call at an indent is
    not marked: that is exactly what the unclosed level is still collecting.

  The message names the line the closer belongs before.
- **A trailing reader prefix.** `'`, `` ` ``, `~`, `~@`, `^`, `@`, `#'`, `#_`
  and a tagged literal each read the form after them, so an appended closer
  becomes that form: `#_)` counts out and does not parse. A lone trailing `\`
  joins them: with no character after it the char rule does not match and it
  falls through to the atom rule, and an appended `)` becomes its character, so
  `\)` counts out, closes nothing, and leaves the file as unbalanced as it
  started while the run reports a repair.

## Where the closers land

End of the last non-blank line, so the repaired form reads the way a human would
have closed it. The exception is a trailing `;` comment: there the closers go on
their own line, since appended inside the comment they would be text and the
file still would not parse. `BalanceReport::endsInLineComment` carries that flag
from the scan.

`isBalanced()` and `isRepairable()` are not opposites. A file can be unbalanced
and unrepairable at once, which is most of the list above, so never infer one
from the other.

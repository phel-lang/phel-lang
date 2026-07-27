# Language Surface Specification

This page is **normative**. It enumerates the part of the Phel language that
[the stability policy](../stability.md) freezes: Phel source that compiles on
`1.0.0` compiles on every later `1.x`.

Anything listed here can change only in a major release, and only after the
[deprecation policy](../stability.md#deprecation-policy-for-1x) has run its course.
Anything not listed is not frozen.

[special-forms.md](../internals/special-forms.md) explains how these are dispatched
and how to add one. This page says which ones exist and that the list is closed.

## 1. Reader syntax

The lexer's token set is the outermost contract: it decides whether a file parses at
all. Every entry below is frozen.

| Syntax | Meaning |
|---|---|
| `( ) [ ] { }` | list, vector, map |
| `#{` | set |
| `'` | quote |
| `` ` `` | quasiquote |
| `~` | unquote |
| `~@` | unquote-splicing |
| `^` | metadata |
| `@` | deref |
| `,` | whitespace (**not** unquote) |
| `;` | line comment |
| `#_` | discard the next form |
| `#(` | short function literal |
| `#'` | var-quote; `#'foo` reads as `(var foo)` |
| `#"…"` | regex literal |
| `#?(` / `#?@(` | reader conditional, splicing reader conditional |
| `##Inf` `##-Inf` `##NaN` | symbolic number literals |
| `#tag` / `#my.app/Tag` | tagged literal |
| `\a` `\space` `\newline` `\tab` `\return` `\formfeed` `\backspace` `\uNNNN` `\oNNN` | character literals |
| `"…"` | string, with `\` escapes |
| `foo#` | auto-gensym inside a quasiquote |

Removed in the run-up to 1.0 and **not** coming back: `#\| \|#` block comments, a bare
`#` comment, `\|()` short functions, `,` and `,@` as unquote, and `foo$` as auto-gensym.
See [removed-deprecated-core-fns.md](../migration/removed-deprecated-core-fns.md).

Still shipped and still deprecated: `\` as a namespace separator, tracked in
[#1567](https://github.com/phel-lang/phel-lang/issues/1567). It is the only reader-level
item whose fate is not yet settled; see
[the deprecated surface map](../migration/deprecated-surface.md).

## 2. Special forms

A list whose head is one of these routes to a dedicated analyzer instead of being a
function call. **The list is closed for 1.x**: entries are not removed and not renamed.
Adding one is a minor-release feature, since existing source cannot be using it.

Trailing `*` marks a low-level form users are not expected to write directly; the
macro that expands into it is named in the last column.

| Form | Kind | Written directly as |
|---|---|---|
| `apply` | core | |
| `break` | core | |
| `def` | core | |
| `defenum*` | type definition | `defenum` |
| `defexception*` | type definition | `defexception` |
| `definterface*` | type definition | `definterface` |
| `defonce` | core | |
| `defstruct*` | type definition | `defstruct` |
| `do` | core | |
| `fn` | core | |
| `foreach` | core | |
| `if` | core | |
| `in-ns` | namespacing | |
| `let` | core | |
| `load` | namespacing | |
| `loop` | core | |
| `new` | interop | |
| `ns` | namespacing | |
| `php/->` | interop | |
| `php/::` | interop | |
| `php/aget` | interop | |
| `php/aget-in` | interop | |
| `php/apush` | interop | |
| `php/apush-in` | interop | |
| `php/aset` | interop | |
| `php/aset-in` | interop | |
| `php/aunset` | interop | |
| `php/aunset-in` | interop | |
| `php/callable` | interop | |
| `php/new` | interop | |
| `php/oset` | interop | |
| `php/ref` | interop | |
| `quote` | core | |
| `recur` | core | |
| `reify*` | type definition | `reify` |
| `set-var` | core | |
| `throw` | core | |
| `try` | core | |
| `use` | namespacing | |
| `var` | core | |

`tests/php/Unit/Architecture/LanguageSurfaceSpecTest.php` parses this table and
compares it against the analyzer's own dispatch registry, so the spec cannot drift
from the compiler. A form added or removed in the analyzer fails the build until this
page is updated too, which is the point at which somebody has to decide whether the
change is allowed inside the major.

### Interop shorthands

The Clojure-style interop spellings are analyzer sugar, not special forms: each expands
to one of the `php/*` entries above before analysis, so they are deliberately absent
from the table.

| Written | Expands to | Position |
|---|---|---|
| `(.m obj args…)` | `(php/-> obj (m args…))` | call |
| `(.-field obj)` | `(php/-> obj field)` | call |
| `(\C/m args…)` | `(php/:: \C (m args…))` | call |
| `(\C. args…)` | `(php/new \C args…)` | call |
| `\C/CONST` | `(php/:: \C CONST)` | value |
| `\C/m` | `(php/callable \C m)` | value |
| `\C/.m` | `(fn [o & args] (apply (php/callable o m) args))` | value |

In value position a qualified member is a class constant unless the class carries no
constant of that name and does carry a public static method, decided by reflection at
analysis time. A class with both keeps the constant. `\C/new` is therefore never a
constructor; see [clojure-divergences.md](clojure-divergences.md).

## 3. The standard library

Every public definition in a `phel.*` namespace is frozen: it keeps its name, and it
keeps every arity it has. A definition may **gain** an arity in a minor; it may not
lose one, and it may not gain a required parameter.

The full list lives in `tests/php/Integration/Api/core-api.snapshot.txt`, one line per
definition with its arities, and `CoreApiSurfaceTest` fails when it drifts. Regenerate
it with:

```bash
composer core-api:update
```

Private definitions (`defn-`, `def-`) are not part of the surface. Neither is anything
under `phel-internal.*`.

### Deliberate divergences from Clojure

Phel tracks Clojure semantics where it can and diverges where PHP makes the Clojure
behaviour wrong or pointless. Those divergences are catalogued in
[clojure-divergences.md](clojure-divergences.md) and marked `:phel` in the
[clojure-test-suite](https://github.com/phel-lang/clojure-test-suite), which runs
nightly. A behaviour listed there is a decision, not a bug.

## 4. Namespaces and project layout

- A namespace name maps to a path. `.` is the separator; `\` still parses and is
  deprecated (#1567).
- `phel.core` is always loaded and must never be `:require`d explicitly.
- The `.phel/` state directory and the `phel-config.php` keys are frozen; see
  [the configuration surface](../stability.md#configuration-surface) and
  [project-layout.md](../project-layout.md).

## 5. Not frozen

- The PHP source text the emitter produces. Only its behaviour is promised.
- Diagnostic wording and error-output shape.
- Macro *expansions*. A macro's expansion may change freely as long as the behaviour
  of the macro does not. Only forms that are themselves special forms are pinned.
- Performance characteristics, beyond the complexity classes documented for the
  persistent collections.
- Anything reachable only through `phel-internal.*`.

## Stated non-goals

These will not arrive in 1.x, and asking for them is answered with the alternative
rather than a roadmap entry:

| Absent | Use instead |
|---|---|
| Software transactional memory, refs | `atom` with `swap!` / `reset!`, and `binding` for dynamic scope |
| Agents | `future` (see the [async guide](https://phel-lang.org/documentation/language/async/)) |
| `core.async`, channels, `go` blocks | PHP fibers and futures under `Fiber/` |
| A self-hosted compiler | The PHP compiler is the only implementation |
| A character type | Character literals read as one-character strings |
| New reader syntax | 1.0 is a promise about what exists |

## See also

- [Stability policy](../stability.md): the PHP half of the same promise
- [Clojure divergences](clojure-divergences.md)
- [Special forms internals](../internals/special-forms.md): dispatch, and how to add one
- [Macros](../internals/macros.md)

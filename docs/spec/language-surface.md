# Language Surface Specification

**Normative.** The part of the language [the stability policy](../stability.md)
freezes: Phel source that compiles on `1.0.0` compiles on every later `1.x`.

Anything listed here changes only in a major, and only after the
[deprecation policy](../stability.md#deprecation-policy-for-1x) has run its
course. Anything not listed is not frozen.
[special-forms.md](../internals/special-forms.md) explains dispatch; this page
says which forms exist and that the list is closed.

## 1. Reader syntax

The lexer's token set decides whether a file parses at all. Every entry is frozen.

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

Removed in the run-up to 1.0 and **not** coming back: `#| |#` block comments, a
bare `#` comment, `|()` short functions, `,` and `,@` as unquote, `foo$`
auto-gensym. See
[removed-deprecated-core-fns.md](../migration/removed-deprecated-core-fns.md).

Still shipped and still deprecated: `\` as a namespace separator
([#2827](https://github.com/phel-lang/phel-lang/issues/2827)). The only
reader-level item whose fate is unsettled.

## 2. Special forms

A list whose head is one of these routes to a dedicated analyzer instead of being
a function call. **The list is closed for 1.x**: entries are not removed and not
renamed. Adding one is a minor feature, since existing source cannot use it.

Trailing `*` marks a low-level form users are not expected to write; the macro
that expands into it is in the last column.

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
compares it against the analyzer's dispatch registry, so the spec cannot drift
from the compiler. A form added or removed in the analyzer fails the build until
this page is updated, which is when somebody decides whether the change is
allowed inside the major.

### Deprecated inside 1.x

Four forms above are frozen but superseded. They keep working for every `1.x`,
warn under `--warn-deprecations`, and are the candidates for removal at the next
major. One rule puts them here:

> **`php/` means host access. It is never a second spelling for something Phel
> already says the Clojure way.**

| Deprecated | Write instead |
|---|---|
| `php/new` | `(new Foo arg)` or `(Foo. arg)` |
| `php/->` | `(.method obj arg)` and `(.-field obj)` |
| `php/::` | `(Foo/method arg)` and `Foo/CONST` |
| `set-var` | `(alter-var-root #'v f)`, or `(set! v x)` for the current binding frame |

The one position that had no spelling, an **assignable static property**, is
`(set! Foo/slot v)` ([#2907](https://github.com/phel-lang/phel-lang/issues/2907)),
which is what Clojure writes for a static field. `set!` tells the two symbol
shapes apart the way the analyzer does everywhere else: a namespace starting with
`\` or an upper-case letter is a class reference and assigns the static property,
anything else is a dynamic var. The primitive underneath stays
`(php/oset (php/:: Foo slot) v)`, now emitting `\Foo::$slot = v`: in assignment
position a bare name can only be the property, since a class constant is not
assignable. Reading it back needs the explicit sigil, `Foo/$slot`, because in
read position the bare name is the constant: PHP keeps the two in separate
namespaces and a class may hold both under one name. Rationale:
[ADR 0013](../adr/0013-static-property-spelling.md).

The remaining `php/*` forms stay, because each reaches a PHP capability Phel has
no other word for: `php/aget`, `php/aset`, `php/apush`, `php/aunset` and
`php/oset` mutate in place, `php/ref` takes a PHP reference, `php/callable` makes
a PHP callable. `phel.core` spells the common ones at top level (`aget`, `aset`,
`aclone`, `alength`, `set!`).

`set-var` carries one extra constraint its removal has to solve first: `binding`
and `with-redefs` expand into it, because an emitted `set-var` inside an open
frame is what records a rebinding. Removing the public form needs a non-public
primitive to take that job.

`LanguageSurfaceSpecTest` checks this table against `SupersededFormDeprecator`
too, so the page and the compiler cannot disagree about which forms warn.
Rationale: [ADR 0007](../adr/0007-clojure-style-interop-is-the-source-spelling.md).

### Interop shorthands

Clojure-style interop spellings are analyzer sugar, not special forms: each
expands to a `php/*` entry above before analysis, which is why they are absent
from the table.

**The shorthand is the only spelling.** The `php/*` forms it expands to are the
compilation target and are deprecated as source. A method or class name computed
at expansion time is reached by building the head symbol,
`(symbol (str "." name))` or `(symbol "Foo" name)`, not by falling back to
`php/->`. Full guide: <https://phel-lang.org/documentation/php-interop/>.

| Written | Expands to | Position |
|---|---|---|
| `(.m obj args…)` | `(php/-> obj (m args…))` | call |
| `(.-field obj)` | `(php/-> obj field)` | call |
| `(C/m args…)` | `(php/:: C (m args…))` | call |
| `(C. args…)` | `(php/new C args…)` | call |
| `C/CONST` | `(php/:: C CONST)` | value |
| `C/$prop` | `(php/:: C $prop)` | value |
| `C/m` | `(php/callable C m)` | value |
| `C/.m` | `(fn [o & args] (apply (php/callable o m) args))` | value |

A root PHP class needs no leading backslash: `PDO/ATTR_ERRMODE` reads the class
constant, `(.-ATTR_ERRMODE PDO)` is the dot-member equivalent. Namespaced classes
have the dotted form, for example
`Symfony.Component.Console.Command.Command/SUCCESS`.

A class name is recognized lexically by an upper-case first segment. Import a
lower-case-initial vendor namespace before using its short name, for example
`(:use phpDocumentor.Reflection.DocBlock)`. Recasing the prefix is not portable:
PHP class names are case-insensitive after loading, but Composer may need the
declared prefix casing to autoload the class first. The destination at the next
major is this dotted spelling without a leading `\`; see
[ADR 0015](../adr/0015-a-php-class-is-named-with-dots.md).

At host-symbol fallback a bare all-caps name reads by position: the global
constant in value position (`PHP_EOL`), the class as a member target, a
constructor argument or a callable (`(WP_CLI/log "x")`, `(php/new PDO dsn)`,
`(php/callable PDO getAvailableDrivers)`). Nothing is probed, so the emitted PHP
does not depend on what the compiling process had autoloaded; see
[ADR 0016](../adr/0016-a-bare-all-caps-host-name-reads-by-position.md). `php/NAME`
keeps the constant reading everywhere, `\NAME` / `(:use NAME)` / `NAME/class`
spell the class in value position, and Phel locals and definitions still resolve
first.

In value position a qualified member is a class constant unless the class has no
constant of that name and does have a public static method, decided by reflection
at analysis time. A class with both keeps the constant, so `C/new` is never a
constructor; see [clojure-divergences.md](clojure-divergences.md). A static
property is the one member the bare name cannot reach: PHP files constants and
static properties separately, so `C/$prop` carries the sigil and needs no
reflection.

## 3. The standard library

Every public definition in a `phel.*` namespace is frozen: it keeps its name and
every arity it has. A definition may **gain** an arity in a minor; it may not lose
one, and may not gain a required parameter.

The full list is `tests/php/Integration/Api/core-api.snapshot.txt`, one line per
definition with its arities, and `CoreApiSurfaceTest` fails on drift. Regenerate:

```bash
composer core-api:update
```

Private definitions (`defn-`, `def-`) are not part of the surface. Neither is
anything under `phel-internal.*`.

Phel tracks Clojure semantics where it can and diverges where PHP makes the
Clojure behaviour wrong or pointless. Those divergences are catalogued in
[clojure-divergences.md](clojure-divergences.md) and marked `:phel` in the
nightly [clojure-test-suite](https://github.com/phel-lang/clojure-test-suite). A
behaviour listed there is a decision, not a bug.

## 4. Namespaces and project layout

- A namespace name maps to a path. `.` is the separator; `\` still parses and is
  deprecated (#2827). It announces without `--warn-deprecations`
  ([ADR 0014](../adr/0014-announce-the-separator-deprecation.md)) and keeps
  working for every `1.x`. At the next major, a PHP class uses the dotted
  upper-case-first spelling above, the leading `\` marker retires, and a `def`
  that shadows a loadable class becomes an error
  ([ADR 0015](../adr/0015-a-php-class-is-named-with-dots.md)).
- `phel.core` is always loaded and must never be `:require`d explicitly.
- The `.phel/` state directory and the `phel-config.php` keys are frozen; see
  [the configuration surface](../stability.md#configuration-surface) and
  [project-layout.md](../project-layout.md).

## 5. Not frozen

- The PHP source the emitter produces. Only its behaviour is promised.
- Diagnostic wording and error-output shape.
- Macro *expansions*. A macro's expansion may change freely as long as its
  behaviour does not; only forms that are themselves special forms are pinned.
- Performance characteristics, beyond the documented complexity classes of the
  persistent collections.
- Anything reachable only through `phel-internal.*`.

## Stated non-goals

Not arriving in 1.x. Asking is answered with the alternative, not a roadmap entry.

| Absent | Use instead |
|---|---|
| Software transactional memory, refs | `atom` with `swap!` / `reset!`, `binding` for dynamic scope |
| Agents | `future` ([async guide](https://phel-lang.org/documentation/language/async/)) |
| `core.async`, channels, `go` blocks | PHP fibers and futures under `Fiber/` |
| A self-hosted compiler | The PHP compiler is the only implementation |
| A character type | Character literals read as one-character strings |
| New reader syntax | 1.0 is a promise about what exists |

## See also

[Stability policy](../stability.md) ·
[Clojure divergences](clojure-divergences.md) ·
[Special forms internals](../internals/special-forms.md) ·
[Macros](../internals/macros.md)

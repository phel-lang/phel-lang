# Deliberate Divergences from Clojure

Phel follows Clojure semantics where it can. Where it does not, the difference is
a decision, and this page is the record. **If a behaviour is listed here, it is
not a bug.** Anything unlisted that differs is worth
[an issue](https://github.com/phel-lang/phel-lang/issues).

Every entry is pinned by a `:phel` reader conditional in
[phel-lang/clojure-test-suite](https://github.com/phel-lang/clojure-test-suite),
run against `main` nightly. That suite characterises Clojure JVM behaviour across
dialects; a `:phel` branch is Phel saying "here, deliberately, something else".

## Why they exist

Five forces produce nearly all of them:

1. **PHP has no character type.** `\a` reads as the one-character string `"a"`.
2. **PHP integers are 64-bit and promote rather than overflow.** No 32-bit `int`,
   no `ArithmeticException`, no JVM float overflow.
3. **PHP comparison is total where Java's is not.** Strings, vectors, maps and
   sets are all structurally comparable, so ordering functions return a value
   where Clojure throws.
4. **Throwing on bad input is expensive without static types,** so many
   predicates and accessors are lenient and return `nil` or `false`.
5. **PHP's own coercion leaks through the numeric casts,** which parse numeric
   strings the way PHP does.

Where Phel is lenient it usually lands where ClojureScript, Basilisp or
ClojureCLR already are; the catalogue notes it when so.

## 1. No character type

`\a`, `\space` and friends read as one-character strings.

| Function | Behaviour |
|---|---|
| `count` | `(count \a)` is `1`, not a throw |
| `ffirst`, `fnext` | seq a one-character string instead of throwing |
| `last`, `reverse` | treat a char as a one-element collection |
| `not-empty` | `(not-empty \a)` is `"a"` |
| `set` | `(set \space)` is `#{" "}` |
| `pr-str`, `prn-str` | print `"A"`, not `\A` (matches ClojureScript and Basilisp) |
| `string/blank?` | `(blank? \space)` is `true`: it really is whitespace |

## 2. Numbers

| Function | Behaviour |
|---|---|
| `inc`, `+'`, `-'`, `*'` | integers promote to BigInt instead of overflowing |
| `-` | integer subtraction overflows into arbitrary precision rather than throwing |
| `*` | on overflow PHP promotes to float; no `ArithmeticException` |
| `int` | 64-bit, so no 32-bit wraparound; parses numeric strings; `nil` is `0` |
| `long` | parses numeric strings, treats `nil` as `0`; still throws on `:0` / `[0]` |
| `double`, `float` | parse numeric strings (PHP float cast); `##Inf` casting is a no-op |
| `byte` | truncates toward zero *before* the range check, so `-128.000001` passes |
| `quot`, `rem`, `mod` | IEEE-754: an infinite or NaN operand yields NaN rather than throwing. Integer division by zero still throws |
| `/` | `(/)` is `1`, the multiplicative identity, mirroring `(*)`. Clojure throws on zero args |
| `numerator`, `denominator` | accept plain integers, treating `n` as `n/1` (matches Basilisp) |

`even?`, `odd?`, `neg?` and `zero?` do not validate that their argument is an
integer: floats, infinities and NaN return a boolean. `zero?` returns `false` for
non-numeric input (matches Basilisp and ClojureScript), `neg?` coerces
`false`/`true` to `0`/`1`, and only `nil` is rejected by `even?`, `odd?`, `neg?`.

## 3. Comparison is total

Clojure throws when asked to order values that are not `Comparable`. Phel
compares them structurally.

| Function | Behaviour |
|---|---|
| `compare` | vectors, lists, sets, maps and ranges compare element-wise or by count. Comparing across *kinds* still throws |
| `min`, `max` | strings compare lexicographically; `nil` is still rejected |
| `min-key`, `max-key` | strings, vectors, maps and sets are comparable, so a value comes back instead of a throw |
| `sort-by` | a non-callable comparator yields an empty result instead of throwing |

## 4. Lenient accessors and predicates

These return `nil` or a benign value where Clojure throws.

| Function | Behaviour |
|---|---|
| `first`, `ffirst` | `nil` for a non-seqable scalar. A keyword still throws |
| `last` | `nil` for a non-seqable scalar |
| `nth` | `(nth nil _)` is `nil` for any index. Out of bounds on a real vector still throws |
| `key` | `nil` for empty or non-pair collections; the first element for sequential pairs |
| `val` | calls `next` and returns the second element; only a non-seqable scalar like `0` throws |
| `keys`, `vals` | `nil` for a non-associative scalar |
| `peek` | lenient on maps (`nil`), lists, cons and lazy seqs (head), strings (last char). Throws only for sets and non-seqable scalars |
| `empty?` | `(not (seq x))` over a lenient `count`; `(empty? 0)` is `true` |
| `dissoc` | accepts a set and removes the element |
| `select-keys` | an empty string behaves as an empty associative source, yielding `{}` |
| `shuffle` | coerces any seqable; `nil` and `{}` yield `[]`, a string shuffles its characters |
| `remove` | regexes and strings are iterable, so a regex yields its pattern characters |
| `realized?` | anything not a pending delay, promise or future is "realized", including `nil` |
| `intern` | auto-creates an unknown target namespace |
| `keyword`, `symbol` | accept symbols and keywords for the ns/name arguments, coercing to their string names |

`drop`, `take`, `nthnext` and `take-nth` treat a `nil` count as `0` rather than
throwing, matching ClojureScript. For `take` this is the transducer arity only;
the lazy-seq arity still throws.

`parse-boolean`, `parse-double`, `parse-long` and `parse-uuid` return `nil` for
anything they cannot parse, including non-strings, so they chain inside `when`
and `if-let` without a guard. Clojure throws on a non-string.

## 5. Stricter than Clojure

The one place Phel is *less* permissive. `phel.string` functions require a
string: `capitalize`, `lower-case`, `upper-case`, `starts-with?` and `ends-with?`
throw on a non-string rather than coercing through `str`. This matches
ClojureScript, Basilisp and ClojureCLR; the JVM's coercing `:default` branch is
the outlier.

## 6. Host interop

### A string receiver is a class name

`(.m x)` reads a string `x` as a **class name**, so `(.cases "\\App\\Status")`
reaches `App\Status::cases()`. Clojure reads the same receiver as the object,
because a JVM `String` has methods and `(.length "abc")` has to work.

The host forces it: PHP strings have no methods, so `$string->m()` is never valid
PHP and there is no behaviour to preserve. Clojure's own answer for a class known
only at runtime is `clojure.lang.Reflector/invokeStaticMethod`, a function rather
than syntax ([#2881](https://github.com/phel-lang/phel-lang/issues/2881)). A
receiver the compiler can prove is an object still emits `->`, so only an
unprovable one carries the runtime test.

### A `def` may shadow a PHP class, and warns

Clojure refuses it outright:

```clojure
user=> (def RuntimeException "shadow")
Syntax error compiling def at (REPL:1:1).
Expecting var, but RuntimeException is mapped to class java.lang.RuntimeException
```

Phel accepts the `def` and warns, because the definition really does win from
there on: the bare-host-symbol fallback resolves a Phel definition before a
class, so `(new DateTime)` after `(def DateTime "shadow")` fails with
`Class "shadow" not found`. The warning names `\DateTime` as the spelling that
still reaches the class.

Warning rather than refusing is a timing decision, not a preference. Refusing is
a breaking change, and the [deprecation policy](../stability.md#deprecation-policy-for-1x)
buys one with a minor of notice first. The refusal belongs to the major that also
drops the leading `\`, because that is when a bare class name has to be
unambiguous ([#2876](https://github.com/phel-lang/phel-lang/issues/2876),
[#2827](https://github.com/phel-lang/phel-lang/issues/2827)). Until then the
leading `\` is the escape, and Clojure needs no equivalent because Java packages
are lower case while PHP's are not. The refusal is what lets the marker retire
rather than become permanent
([ADR 0015](../adr/0015-a-php-class-is-named-with-dots.md)).

### `Class/new` is not a constructor

Clojure 1.12 reads `File/new` in value position as the constructor. Phel does not:
`\C/new` keeps meaning the class constant `new`.

PHP 7 lifted the ban on reserved words as member names, so `Foo::new()` is both
legal and a common named-constructor idiom, and one class can carry a constant
`new` and a static method `new` at once. Claiming the name would silently change
what existing code reads: `(\League\Uri\Urn/new "urn:isbn:1234")` already calls a
real `::new()` factory, and `league/uri` and `phpbench` both ship one. Java
forbids the name outright, which is what let Clojure take it safely;
[Basilisp declined it](https://docs.basilisp.org/en/latest/differencesfromclojure.html#host-interop)
for the same reason Python does not.

A constructor as a value stays `(fn [x] (new \C x))`. The two safe halves of the
Clojure 1.12 syntax are supported: `\C/m` is a static method as a value, `\C/.m`
an instance method as a function of its receiver
([#2883](https://github.com/phel-lang/phel-lang/issues/2883)). Where a class
carries a constant *and* a static method under one name the constant wins, as it
did before the value-position forms existed; the shadowed method stays reachable
as `(\C/m x)`.

The suite does not pin this one, because no working program can observe it: a
string receiver was an error before and after, and only the message differs. It is
listed because the *capability* is a difference a Clojure reader will notice.

### A static property is read with a `$` sigil

Clojure reads a static field and a constant with one spelling, `Classname/field`,
because the JVM has no separate constant namespace. PHP has two, and a class may
carry `const slot` and `public static $slot` at once, so Phel keeps the bare name
on the constant it has always meant and spells the property `\C/$prop`
([#2907](https://github.com/phel-lang/phel-lang/issues/2907)).

Assignment needs no sigil: a class constant cannot be assigned, so
`(set! \C/slot v)` and `(php/oset (php/:: \C slot) v)` can only mean the property
and emit `\C::$slot = v`.

The sigil is rejected anywhere it could not mean a static property, `(php/-> o $x)`
and `(php/:: \C ($x))` among them. PHP would read the `$x` as one of its own
variables, which no Phel binding defines
([#2915](https://github.com/phel-lang/phel-lang/issues/2915)).

### `aset` and `set!` are macros, not functions

Clojure's `aset` is a function, so `(map (partial aset arr) …)` works. Phel's is a
**macro**, and so is `set!`.

PHP arrays are value types: a function receiving one receives a copy, so a
function `aset` would mutate the copy and drop the write. `set!` is a macro
because its first argument is a location (`(.-field o)`), not a value.

Neither can be passed to a higher-order function. Where Clojure would use
`(partial aset arr)`, wrap it: `(fn [i v] (aset arr i v))`.

### Mutation naming: which forms got Clojure names

`set!` is the one mutating `php/*` form with a Clojure spelling
([#2884](https://github.com/phel-lang/phel-lang/issues/2884)). The rest keep the
prefix, deliberately:

| Form | Decision | Why |
|---|---|---|
| `php/oset` | `set!` in `phel.core` | Clojure spells this exact operation `(set! (.-field o) v)` |
| `php/aset`, `php/aget`, `php/aclone`, `php/alength` | core names since [#1411](https://github.com/phel-lang/phel-lang/issues/1411) | same names Clojure uses |
| `php/apush`, `php/aunset` | stay `php/*` | JVM arrays are fixed size, so Clojure has no counterpart and any core name would be invented. 1.0 freezes whatever ships, so an invented name is the expensive kind of guess |
| `php/ref`, `php/callable` | stay `php/*` | both take an **unevaluated** form, so neither can be a plain function, and both name a host mechanism with no Clojure analogue |

### Var mutation: three operations, three names

Clojure overloads `set!` across a field and a thread-local binding, and gives root
mutation its own name. Phel matches that, plus one name it inherited:

| Operation | Clojure | Phel |
|---|---|---|
| assign an object field | `(set! (.-f o) v)` | `(set! (.-f o) v)` |
| assign a static field | `(set! Foo/staticField v)` | `(set! \Foo/slot v)` |
| assign the current thread-local binding | `(set! *x* v)` | `(set! *x* v)`, or `(var-set #'*x* v)` |
| change the root | `(alter-var-root #'*x* f)` | `(alter-var-root #'*x* f)` |
| *(no Clojure counterpart)* | | `(set-var *x* v)`, a special form writing the root directly, **deprecated** |

`set!` on a symbol writes only the binding frame and **throws when none is
active**, so it can never change a root by accident, exactly as in Clojure.

`set-var` is the odd one out: a special form, taking a value rather than a
function, with a name that reads like Clojure's `set!` while behaving like
`alter-var-root`. A name pointing a Clojure reader at the wrong operation is the
failure mode this page exists to prevent, so it is deprecated
([#2888](https://github.com/phel-lang/phel-lang/issues/2888)). It is on the closed
special-form list, so it works throughout `1.x` and can only be removed at a
major.

```phel
(set-var *x* 3)                        (alter-var-root #'*x* (constantly 3))
```

The call shapes differ, which is why this is a deprecation and not a rename:
`set-var` takes a symbol and a value, `alter-var-root` a var and a function.

## 7. Absent concepts

| Clojure | Phel |
|---|---|
| `aclone`, reference identity | PHP arrays are value types; nothing to alias ([#1735](https://github.com/phel-lang/phel-lang/issues/1735)) |
| `special-symbol?` | Phel does not recognise the JVM special-symbol set |
| Class objects (`string?` on a class) | classes are represented as strings |

## 8. Known gap, not a decision

`case` returns `nil` when nothing matches and there is no default clause; Clojure
throws. The suite marks it `:phel` with a note that it is arguably a real semantic
gap. It is the one entry here that may yet change.

## Keeping this page honest

The suite is the source of truth. To re-derive the list:

```bash
git clone --depth 1 https://github.com/phel-lang/clojure-test-suite
grep -rn -B4 ':phel' clojure-test-suite/test --include='*.cljc'
```

Each `:phel` branch carries a comment explaining the divergence. A new one absent
from this page means the page is stale.

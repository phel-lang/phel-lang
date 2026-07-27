# Deliberate Divergences from Clojure

Phel follows Clojure semantics where it can. Where it does not, the difference is a
decision, and this page is the record of it. **If a behaviour is listed here, it is not
a bug.** Anything not listed that differs from Clojure is worth
[an issue](https://github.com/phel-lang/phel-lang/issues).

Every entry is pinned by a `:phel` reader conditional in
[phel-lang/clojure-test-suite](https://github.com/phel-lang/clojure-test-suite), which
runs against `main` nightly. That suite characterises Clojure JVM behaviour across
dialects; a `:phel` branch is Phel saying "here, deliberately, something else".

## Why they exist

Five forces produce nearly all of them:

1. **PHP has no character type.** `\a` reads as the one-character string `"a"`.
2. **PHP integers are 64-bit and promote rather than overflow.** There is no 32-bit
   `int`, no `ArithmeticException`, and no JVM float overflow.
3. **PHP comparison is total where Java's is not.** Strings, vectors, maps and sets are
   all structurally comparable, so ordering functions return a value where Clojure
   throws.
4. **Throwing on bad input is expensive in a language with no static types**, so many
   predicates and accessors are lenient and return `nil` or `false` instead.
5. **PHP's own type coercion leaks through the numeric casts**, which parse numeric
   strings the way PHP does.

Where Phel is lenient it usually lands where ClojureScript, Basilisp or ClojureCLR
already are; the catalogue notes it when so.

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
| `quot`, `rem`, `mod` | IEEE-754 semantics: an infinite or NaN operand yields NaN rather than throwing. Integer division by zero still throws |
| `/` | `(/)` is `1`, the multiplicative identity, mirroring `(*)`. Clojure throws on zero args |
| `numerator`, `denominator` | accept plain integers, treating `n` as `n/1` (matches Basilisp) |

### Lenient numeric predicates

`even?`, `odd?`, `neg?` and `zero?` do not validate that their argument is an integer.
Floats, infinities and NaN return a boolean instead of throwing. `zero?` returns
`false` for non-numeric input rather than throwing (matches Basilisp and
ClojureScript). `neg?` coerces `false`/`true` to `0`/`1`. Only `nil` is rejected by
`even?`, `odd?` and `neg?`.

## 3. Comparison is total

Clojure throws when asked to order values that are not `Comparable`. Phel compares them
structurally.

| Function | Behaviour |
|---|---|
| `compare` | vectors, lists, sets, maps and ranges compare element-wise or by count. Comparing across *kinds* (vector vs map) still throws |
| `min`, `max` | strings compare lexicographically; `nil` is still rejected |
| `min-key`, `max-key` | strings, vectors, maps and sets are all comparable, so a value comes back instead of a throw |
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
| `keys`, `vals` | `nil` for a non-associative scalar instead of throwing |
| `peek` | lenient on maps (`nil`), lists, cons and lazy seqs (head), and strings (last char). Throws only for sets and non-seqable scalars |
| `empty?` | `(not (seq x))` over a lenient `count`; `(empty? 0)` is `true` |
| `dissoc` | accepts a set and removes the element |
| `select-keys` | an empty string behaves as an empty associative source and yields `{}` |
| `shuffle` | coerces any seqable; `nil` and `{}` yield `[]`, a string shuffles its characters |
| `remove` | regexes and strings are iterable, so a regex yields its pattern characters |
| `realized?` | anything not a pending delay, promise or future is "realized", including `nil` |
| `intern` | auto-creates an unknown target namespace instead of throwing |
| `keyword`, `symbol` | accept symbols and keywords for the ns/name arguments and coerce to their string names |

### `nil`-punned counts

`drop`, `take`, `nthnext` and `take-nth` treat a `nil` count as `0` rather than
throwing, matching ClojureScript. For `take` this is the transducer arity only; the
lazy-seq arity still throws.

### `nil`-safe parsers

`parse-boolean`, `parse-double`, `parse-long` and `parse-uuid` return `nil` for any
input they cannot parse, including non-strings, so they chain inside `when` and
`if-let` without a guard. Clojure throws on a non-string.

## 5. Stricter than Clojure

The one place Phel is *less* permissive. `phel.string` functions require a string:
`capitalize`, `lower-case`, `upper-case`, `starts-with?` and `ends-with?` throw on a
non-string rather than coercing through `str`. This matches ClojureScript, Basilisp and
ClojureCLR; the JVM's coercing `:default` branch is the outlier.

## 6. Host interop

### A string receiver is a class name

`(.m x)` reads a string `x` as a **class name**, so `(.cases "\\App\\Status")` reaches
`App\Status::cases()`. Clojure reads the same receiver as the object, because a JVM
`String` has methods and `(.length "abc")` has to work.

The difference is forced by the host: PHP strings have no methods at all, so
`$string->m()` is never valid PHP and there is no behaviour to preserve. Reading the
receiver as a class name is therefore free here and impossible there. Clojure's own
answer for a class known only at runtime is `clojure.lang.Reflector/invokeStaticMethod`,
a function rather than syntax ([#2881](https://github.com/phel-lang/phel-lang/issues/2881)).

A receiver the compiler can prove is an object still emits `->`, so only an unprovable
one carries the runtime test.

### `Class/new` is not a constructor

Clojure 1.12 reads `File/new` in value position as the constructor. Phel does not, and
`\C/new` keeps meaning the class constant `new`.

PHP 7 lifted the ban on reserved words as member names, so `Foo::new()` is both legal
and a common named-constructor idiom, and a single class can carry a constant `new` and
a static method `new` at once. Claiming the name would silently change what existing
code reads: `(\League\Uri\Urn/new "urn:isbn:1234")` already calls a real `::new()`
factory, and `league/uri` and `phpbench` both ship one. Java forbids the name outright,
which is what let Clojure take it safely;
[Basilisp declined it](https://docs.basilisp.org/en/latest/differencesfromclojure.html#host-interop)
for the same reason Python does not.

A constructor as a value stays `(fn [x] (new \C x))`. The two safe halves of the Clojure
1.12 syntax are supported: `\C/m` is a static method as a value and `\C/.m` is an
instance method as a function of its receiver
([#2883](https://github.com/phel-lang/phel-lang/issues/2883)).

Where a class carries a constant *and* a static method under one name, the constant
wins, which is what happened before the value-position forms existed. The shadowed
method stays reachable in call position, `(\C/m x)`, and as `(fn [x] (\C/m x))`.

This is the one entry the suite does not pin, because no working program can observe it:
a string receiver was an error before the change and is an error after it, and only the
message differs. It is listed because the *capability* is a difference a Clojure reader
will notice, not because a behaviour changed under them.

### `aset` and `set!` are macros, not functions

Clojure's `aset` is a function, so `(map (partial aset arr) …)` and any other
higher-order use works. Phel's is a **macro**, and so is `set!`.

PHP arrays are value types: a function receiving one receives a copy, so a function
`aset` would mutate the copy and drop the write. `set!` is a macro for the usual reason
a place-setting form is: its first argument is a location (`(.-field o)`), not a value.

The practical consequence is that neither can be passed to a higher-order function.
Where Clojure would use `(partial aset arr)`, wrap it: `(fn [i v] (aset arr i v))`.

### Mutation naming: which forms got Clojure names

`set!` is the one mutating `php/*` form with a Clojure spelling, and it has it
([#2884](https://github.com/phel-lang/phel-lang/issues/2884)). The rest keep the `php/`
prefix, deliberately:

| Form | Decision | Why |
|---|---|---|
| `php/oset` | `set!` in `phel.core` | Clojure spells this exact operation `(set! (.-field o) v)` |
| `php/aset`, `php/aget`, `php/aclone`, `php/alength` | core names since [#1411](https://github.com/phel-lang/phel-lang/issues/1411) | same names Clojure uses |
| `php/apush`, `php/aunset` | stay `php/*` | JVM arrays are fixed size, so Clojure has no counterpart and any core name would be invented. 1.0 freezes whatever ships, so an invented name is the expensive kind of guess; revisit when a concrete need names it |
| `php/ref`, `php/callable` | stay `php/*` | both take an **unevaluated** form (a variable to reference, a call target), so neither can be a plain function, and both name a host mechanism with no Clojure analogue |

`set-var` is a separate question, tracked in
[#2888](https://github.com/phel-lang/phel-lang/issues/2888): it writes a var's root, so
the Clojure name for it is `alter-var-root` rather than `set!`, and Clojure's
thread-local var `set!` has no Phel equivalent yet.

## 7. Absent concepts

| Clojure | Phel |
|---|---|
| `aclone`, reference identity | PHP arrays are value types; there is nothing to alias ([#1735](https://github.com/phel-lang/phel-lang/issues/1735)) |
| `special-symbol?` | Phel does not recognise the JVM special-symbol set |
| Class objects (`string?` on a class) | classes are represented as strings |

## 8. Known gap, not a decision

`case` returns `nil` when nothing matches and there is no default clause. Clojure
throws. The suite marks this `:phel` with a note that it is arguably a real semantic
gap rather than an intended divergence. It is the one entry on this page that may yet
change; everything else is settled.

## Keeping this page honest

The suite is the source of truth. To re-derive the list:

```bash
git clone --depth 1 https://github.com/phel-lang/clojure-test-suite
grep -rn -B4 ':phel' clojure-test-suite/test --include='*.cljc'
```

Each `:phel` branch carries a comment explaining the divergence. A new one that is not
represented here means this page is stale.

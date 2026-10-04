# Deliberate Divergences from Clojure

Phel follows Clojure semantics where it can. Where it does not, the difference is
a decision, and this page is the record. **If a behaviour is listed here, it is
not a bug**, except for the known gaps in section 11. Anything unlisted that differs is worth
[an issue](https://github.com/phel-lang/phel-lang/issues).

Behaviour shared with Clojure is pinned by a `:phel` reader conditional in
[phel-lang/clojure-test-suite](https://github.com/phel-lang/clojure-test-suite),
run against `main` nightly. That suite characterises Clojure JVM behaviour across
dialects; a `:phel` branch is Phel saying "here, deliberately, something else".
Host interop (section 7) has no Clojure counterpart to test against; it follows
the ADRs it links. The rows the suite has no file for are pinned by tests in this
repository, named in section 9.

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
| `char?` | `true` for any one-character string: `(char? "0")` |
| `pr-str`, `prn-str` | print `"A"`, not `\A` (matches ClojureScript and Basilisp) |
| `string/blank?` | `(blank? \space)` is `true`: it really is whitespace |

## 2. Numbers

| Function | Behaviour |
|---|---|
| `inc`, `dec`, `+`, `*`, `+'`, `-'`, `*'` | integer overflow promotes to BigInt; no `ArithmeticException` |
| `-` | `(-)` is `0`; Clojure throws on zero args. Integer overflow promotes to BigInt, as above |
| `int?`, `pos-int?`, `neg-int?` | `true` for a BigInt literal such as `1N` |
| `int` | 64-bit, so no 32-bit wraparound; parses numeric strings; `nil` is `0` |
| `long` | parses numeric strings, treats `nil` as `0`; still throws on `:0` / `[0]` |
| `double`, `float` | parse numeric strings (PHP float cast); `##Inf` casting is a no-op |
| `byte` | truncates toward zero *before* the range check, so `-128.000001` passes |
| `quot` | IEEE-754 rather than a throw: `(quot ##Inf 2)` is `##Inf`, `(quot ##-Inf 1)` is `##-Inf`, a NaN operand yields NaN. Division by zero still throws |
| `rem`, `mod` | IEEE-754: an infinite or NaN operand yields NaN rather than throwing. Division by zero still throws |
| `NaN?` | `(NaN? nil)` is `false` rather than a throw; a string still throws |
| `repeat` | a fractional count rounds up: `(repeat 3.14 :a)` has four elements (matches ClojureScript) |
| `/` | `(/)` is `1`, the multiplicative identity, mirroring `(*)`. Clojure throws on zero args |
| `numerator`, `denominator` | accept plain integers, treating `n` as `n/1` (matches Basilisp) |

`even?`, `odd?`, `neg?` and `zero?` do not validate that their argument is an
integer: floats, infinities and NaN return a boolean. `zero?` returns `false` for
non-numeric input (matches Basilisp and ClojureScript), `neg?` and `pos?` coerce
`false`/`true` to `0`/`1`, and only `nil` is rejected by `even?`, `odd?`, `neg?`
and `pos?`.

## 3. Comparison is total

Clojure throws when asked to order values that are not `Comparable`. Phel
compares them structurally.

| Function | Behaviour |
|---|---|
| `compare` | vectors, lists, sets and maps compare element-wise or by count. Comparing across *kinds* still throws. Lazy seqs, `range` included, are a known gap (section 11) |
| `min`, `max` | strings compare lexicographically; `nil` is still rejected |
| `min-key`, `max-key` | strings, vectors, maps and sets are comparable, so a value comes back instead of a throw |
| `sort-by` | a `nil`, `[]` or `{}` comparator yields an empty result instead of throwing. Other non-callable comparators, such as `5` or `:b`, throw |

## 4. Lenient accessors and predicates

These return `nil` or a benign value where Clojure throws.

| Function | Behaviour |
|---|---|
| `first`, `ffirst` | `nil` for a non-seqable scalar: `(first 5)` and `(first true)` are `nil`. A keyword still throws |
| `last` | `(last 0)` is `nil`. Other scalars, such as `5`, `1.5`, `true` or a keyword, throw |
| `nth` | `(nth nil _)` is `nil` for any index. Out of bounds on a real vector still throws |
| `key` | `nil` for empty or non-pair collections; the first element for sequential pairs |
| `val` | calls `next` and returns the second element; only a non-seqable scalar like `0` throws |
| `keys`, `vals` | `(keys 0)` and `(vals 0)` are `nil`, because `(empty? 0)` is `true`. Other scalars, such as `5`, `0.0` or `true`, throw |
| `contains?` | on a string, checks numeric indices only: `(contains? "abc" "a")` is `false` rather than a throw (matches ClojureScript) |
| `conj`, `conj!`, `merge` | a two-element list onto a map is a map entry: `(conj {:a 0} '(:b 1))` is `{:a 0 :b 1}` (matches Basilisp). Any other length still throws |
| `associative?` | `true` for a PHP array, and for `(seq "ab")`, which is the vector `["a" "b"]` |
| `descendants` | `nil` for a PHP class, `(descendants stdClass)` among them, rather than a throw |
| `peek` | lenient on maps (`nil`), lists, cons and lazy seqs (head), strings (last char). Throws only for sets and non-seqable scalars |
| `empty?` | `(empty? 0)` is `true` while `(seq 0)` throws, so it is not `(not (seq x))` for scalars. `(empty? 0.0)` and `(empty? 5)` are `false`; `true` and a keyword throw |
| `dissoc` | accepts a set and removes the element |
| `select-keys` | an empty string behaves as an empty associative source, yielding `{}` |
| `shuffle` | coerces any seqable; `nil` and `{}` yield `[]`, a string shuffles its characters |
| `remove` | regexes and strings are iterable, so a regex yields its pattern characters |
| `realized?` | anything not a pending delay, promise or future is "realized", including `nil` |
| `assoc`, `dissoc`, `conj` on a transient | accepted and applied, where Clojure requires `assoc!`, `dissoc!`, `conj!`. `pop` and `disj` on a transient still throw |
| `transient` | accepts a sorted map or sorted set |
| `intern` | auto-creates an unknown target namespace |
| `keyword`, `symbol` | accept symbols and keywords for the ns/name arguments, coercing to their string names. `(keyword "abc" nil)` is `nil` instead of a throw |

`(first 5)` is the case a Clojure reader hits first: Clojure throws
`Don't know how to create ISeq from: java.lang.Long`. Phel keeps `nil` through
1.x. Code that passes a scalar to `first` today reads the `nil` as "nothing
there", and turning that into a throw would break it, which the
[language stability promise](../stability.md#two-promises) rules out inside a
major. The leniency stops at the accessors in this table: `(seq 5)`, `(rest 5)` and
`(next 5)` throw, as in Clojure, so do not read a `nil` from `first` as proof that
the argument was a collection
([#3477](https://github.com/phel-lang/phel-lang/issues/3477)).

A `nil` count reads as `0` rather than throwing in `nthnext`, in the lazy arity
of `take-nth`, and in the transducer arities of `drop` and `take`, matching
ClojureScript. So `(nthnext [1 2] nil)` is `(1 2)` and `(into [] (take nil) [1 2])`
is `[]`. The collection arities of `drop` and `take` still throw, as does the
`take-nth` transducer.

`parse-boolean`, `parse-double`, `parse-long` and `parse-uuid` return `nil` for
anything they cannot parse, including non-strings, so they chain inside `when`
and `if-let` without a guard. Clojure throws on a non-string.

## 5. Stricter than Clojure

The one place Phel is *less* permissive. `phel.string` functions require a
string: `capitalize`, `lower-case`, `upper-case`, `starts-with?` and `ends-with?`
throw on a non-string rather than coercing through `str`. This matches
ClojureScript, Basilisp and ClojureCLR; the JVM's coercing `:default` branch is
the outlier.

## 6. `sort-by` calls its key function once per element

Clojure's `sort-by` builds a comparator that applies `keyfn` to both arguments,
so the key function runs twice per comparison, O(n log n) times. Phel computes
each key once, sorts `[key value]` pairs, and discards the keys. Sorting 100
elements calls the key function 100 times where Clojure calls it about 1114.

The sorted result is identical. Only the number of invocations differs, so this
is invisible to a pure key function and visible to one that counts calls, logs,
or memoises internally.

The trade was taken because the key function is user code on the hot path of a
common operation: with a key function as ordinary as `#(get % :id)` the
transform is 2.2x faster, and the gap widens with `n` because the saved calls
grow as `n log n` while the computed keys grow as `n`.

`sort` is unaffected. It applies the comparator to elements directly and there
is no key function to call.

## 7. Host interop

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
`C/new` keeps meaning the class constant `new`.

PHP 7 lifted the ban on reserved words as member names, so `Foo::new()` is both
legal and a common named-constructor idiom, and one class can carry a constant
`new` and a static method `new` at once. Claiming the name would silently change
what existing code reads: `(League.Uri.Urn/new "urn:isbn:1234")` already calls a
real `::new()` factory, and `league/uri` and `phpbench` both ship one. Java
forbids the name outright, which is what let Clojure take it safely;
[Basilisp declined it](https://docs.basilisp.org/en/latest/differencesfromclojure.html#host-interop)
for the same reason Python does not.

A constructor as a value stays `(fn [x] (new C x))`. The two safe halves of the
Clojure 1.12 syntax are supported: `C/m` is a static method as a value, `C/.m`
an instance method as a function of its receiver
([#2883](https://github.com/phel-lang/phel-lang/issues/2883)). Where a class
carries a constant *and* a static method under one name the constant wins, as it
did before the value-position forms existed; the shadowed method stays reachable
as `(C/m x)`.

The suite does not pin this one, because no working program can observe it: a
string receiver was an error before and after, and only the message differs. It is
listed because the *capability* is a difference a Clojure reader will notice.

### A static property is read with a `$` sigil

Clojure reads a static field and a constant with one spelling, `Classname/field`,
because the JVM has no separate constant namespace. PHP has two, and a class may
carry `const slot` and `public static $slot` at once, so Phel keeps the bare name
on the constant it has always meant and spells the property `C/$prop`
([#2907](https://github.com/phel-lang/phel-lang/issues/2907)).

Assignment needs no sigil: a class constant cannot be assigned, so
`(set! C/slot v)` and `(php/oset (php/:: C slot) v)` can only mean the property
and emit `\C::$slot = v`.

The sigil is rejected anywhere it could not mean a static property, `(php/-> o $x)`
and `(php/:: C ($x))` among them. PHP would read the `$x` as one of its own
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
| assign a static field | `(set! Foo/staticField v)` | `(set! Foo/slot v)` |
| assign the current thread-local binding | `(set! *x* v)` | `(set! *x* v)`, or `(var-set #'*x* v)` |
| change the root | `(alter-var-root #'*x* f)` | `(alter-var-root #'*x* f)` |
| *(no Clojure counterpart)* | | `(set-var *x* v)`, an internal special form writing the root directly, **rejected as source** |

`set!` on a symbol writes only the binding frame and **throws when none is
active**, so it can never change a root by accident, exactly as in Clojure.

`set-var` is the odd one out: a special form, taking a value rather than a
function, with a name that reads like Clojure's `set!` while behaving like
`alter-var-root`. A name pointing a Clojure reader at the wrong operation is the
failure mode this page exists to prevent, so it is rejected as source since
`0.52.0`, with `PHEL012` ([#2888](https://github.com/phel-lang/phel-lang/issues/2888), ADR 0018).
It remains on the closed special-form list because `binding` and `with-redefs`
expand into it internally.

```phel
(set-var *x* 3)                        (alter-var-root #'*x* (constantly 3))
```

The call shapes differ, which is why this was not a rename: `set-var` takes a
symbol and a value, `alter-var-root` a var and a function.

## 8. Calls

Clojure checks every call's argument count when the call runs. Phel checks a
call by name at compile time, and a call through a value only partly:

| Call | Too few arguments | Too many arguments |
|---|---|---|
| a known fn by name, `(f 1 2)` | `PHEL002` at compile time | `PHEL002` at compile time |
| a single-arity fn value: a local, `apply`, a higher-order fn | `PHEL401` at run time | **ignored** |
| a multi-arity fn value | throws at run time | throws at run time |

So `((fn [x] x) 1 2)` is `1`, `(apply inc 1 [17])` is `2`, and
`(update {:a 1} :a identity 2 3)` is `{:a 1}`, because `identity` drops the
extra arguments `update` passes it. Clojure throws on all three. ClojureScript
behaves like Phel.

Two reasons keep the runtime path open
([#3384](https://github.com/phel-lang/phel-lang/issues/3384),
[#3395](https://github.com/phel-lang/phel-lang/pull/3395)). Every call goes
through the fn's `__invoke`, and a count guard there costs about 6% on a trivial
body. And PHP passes callbacks more arguments than they declare by design:
`array_walk` passes the key, `set_error_handler` four values. A runtime throw
would break working interop code with no way to opt out.

## 9. Reader and core macros

| Form | Behaviour |
|---|---|
| `cond` | an odd trailing form is the default: `(cond false 1 2)` is `2`. Clojure rejects an odd number of forms |
| string literals | an unknown escape keeps its backslash, as in PHP: `"a\qb"` is four characters. Clojure's reader rejects it |
| `for` | each binding is a `binding :verb expr` triple, with `:in`, `:range`, `:keys` or `:pairs`. The Clojure pair form `(for [x [1 2 3]] x)` fails to expand with `PHEL005`; write `(for [x :in [1 2 3]] x)`. `doseq` accepts the pair form |

The suite has no file for `cond`, `for` or the string reader, so these rows are
pinned in this repository instead: `tests/phel/core/control-structures.phel`
(`cond`), `tests/phel/reader.phel` (string escapes) and
`tests/phel/core/for-loop.phel` (`for`).

## 10. Absent concepts

| Clojure | Phel |
|---|---|
| `aclone`, `vec` aliasing an array, reference identity | PHP arrays are value types; nothing to alias ([#1735](https://github.com/phel-lang/phel-lang/issues/1735)) |
| `special-symbol?` | Phel does not recognise the JVM special-symbol set |
| Class objects (`string?` on a class) | classes are represented as strings |
| refs and agents | not implemented: `ref` and `agent` do not resolve, so `add-watch` takes an atom or a var |
| Pattern objects | a regex literal is its PHP pattern string, so `(= #"a" #"a")` is `true` (matches Basilisp) |

## 11. Known gaps, not decisions

`case` returns `nil` when nothing matches and there is no default clause; Clojure
throws. The suite marks it `:phel` with a note that it is arguably a real semantic
gap.

The suite also pins these with a `:phel` branch, but they are bugs, each with an
open issue. Expect them to change:

| Clojure | Phel today |
|---|---|
| `(compare (range 5) (range 5))` is `0` | `1`: two lazy seqs fall through to PHP `<=>` on objects ([#3555](https://github.com/phel-lang/phel-lang/issues/3555)) |
| `(set {:a 1 :b 2})` is `#{[:a 1] [:b 2]}` | `#{1 2}`: `set` collects a map's values ([#3556](https://github.com/phel-lang/phel-lang/issues/3556)) |
| `(repeatedly 1/2 +)` is `(0)` | a ratio count throws a `TypeError` in `repeat` and `repeatedly` ([#3557](https://github.com/phel-lang/phel-lang/issues/3557)) |

## Keeping this page honest

The suite is the source of truth. To re-derive the list:

```bash
git clone --depth 1 https://github.com/phel-lang/clojure-test-suite
grep -rn -B4 ':phel' clojure-test-suite/test --include='*.cljc'
```

Each `:phel` branch carries a comment explaining the divergence. A new one absent
from this page means the page is stale.

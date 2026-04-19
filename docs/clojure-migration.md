# Clojure to Phel Migration Guide

Phel is a functional Lisp inspired by Clojure that compiles to PHP. If you know Clojure, you already know most of Phel. This guide covers the differences. Jump to [Quick reference](#quick-reference) if you just want the at-a-glance view.

## Quick reference

| Clojure | Phel | Notes |
|---------|------|-------|
| `(ns my.app (:require [clojure.string :as str]))` | `(ns my\app (:require phel\string :as str))` | `\` separator; `.` also works |
| `(.method obj arg)` | `(php/-> obj (method arg))` | Instance method |
| `(Class/staticMethod arg)` | `(php/:: Class (method arg))` | Static method |
| `(ClassName. arg)` | `(ClassName. arg)`, `(new ClassName arg)`, or `(php/new ClassName arg)` | `ClassName.` and `new` both read as `(php/new ClassName ...)` |
| `(.-field obj)` | `(php/-> obj -field)` | Property access |
| `(:import [java.util Date])` | `(:use DateTime)` in the `ns` form | Imports a PHP class by short name; also works with FQNs: `(:use Phel\Lang\Symbol)` |
| `(instance? Type x)` | `(instance? Type x)` or `(php/instanceof x Type)` | Phel ships an `instance?` macro that wraps `php/instanceof` with Clojure's argument order |
| `(class x)` | `(type x)` | Returns a keyword like `:string`, `:int`, `:hash-map` |
| `(subs s start end)` | `(phel\string\slice s start end)` | Substring |
| `(clojure.string/upper-case s)` | `(phel\string\upper-case s)` | String utils in `phel\string` |

## What's the same

Most of Clojure's core library works identically in Phel:

- **Data structures**: persistent vectors `[]`, maps `{}`, sets, lists `'()`, lazy-seqs, sorted collections (`sorted-map`, `sorted-map-by`, `sorted-set`, `sorted-set-by`)
- **Collection API**: `conj`, `conj!`, `disj`, `into`, `assoc`, `dissoc`, `get`, `get-in`, `update`, `update-in`, `merge`, `select-keys`, `keys`, `vals`, `update-keys`, `update-vals`, `rename-keys`, `zipmap`, `frequencies`, `group-by`
- **Sequence API**: `map`, `filter`, `remove`, `reduce`, `first`, `rest`, `next`, `cons`, `nth`, `last`, `butlast`, `take`, `drop`, `drop-last`, `partition`, `sort-by`, `min-key`, `max-key`, `run!`
- **Higher-order**: `comp`, `partial`, `juxt`, `apply`, `fnil`, `some-fn`
- **Predicates**: `some?`, `seq?`, `map?`, `set?`, `vector?`, `list?`, `coll?`, `counted?`, `sorted?`, `char?`, `integer?`, `boolean`, `nan?` / `NaN?`
- **Coercions**: `str`, `keyword`, `symbol`, `name`, `namespace`, `vec`, `float`, `double`, `byte`, `char`, `array-map`, `to-array`, `object-array`, `aclone`
- **Macros**: `defn`, `def`, `defmacro`, `let`, `letfn`, `if`, `when`, `cond`, `case`, `condp`, `->`, `->>`, `as->`, `some->`, `some->>`, `cond->`, `cond->>`, `and`, `or`, `not`, `loop`/`recur`, `doseq`, `dotimes`, `for`, `if-some`, `when-some`, `when-first`, `assert`
- **Protocols and types**: `defprotocol`, `extend-type`, `extend-protocol`, `satisfies?`, `extends?`, `reify`, `defrecord`, `deftype`
- **Multimethods**: `defmulti`, `defmethod`, `isa?`, `derive`, `parents`, `ancestors`, `make-hierarchy`
- **Destructuring**: vectors, maps, `:keys`, `:strs`, `:syms`, `:or`, `& rest`
- **Metadata**: `meta`, `with-meta`, `vary-meta`, `^:keyword` reader syntax
- **Atoms**: `atom`, `deref` / `@`, `swap!`, `reset!`, `add-watch`, `remove-watch`, `set-validator!`
- **Taps**: `tap>`, `add-tap`, `remove-tap` (synchronous dispatch; no background queue)
- **Delays and futures**: `delay`, `force`, `delay?`, `realized?`, `future`, `future-cancel`, `future-cancelled?`, `future-done?` (fiber-based via AMPHP)
- **Exceptions**: `ex-info`, `ex-data`, `ex-message`, `ex-cause`, `throw`, `try` / `catch` / `finally`
- **Transducers**: `transduce`, `into` (3-arg), `sequence`, `completing`, `cat`, plus transducer arities for `map`, `filter`, `take`, `drop`, `keep`, `distinct`, `dedupe`, `mapcat`, `interpose`, and more
- **Testing**: `deftest`, `is`, `testing`, `are`, `do-report`, extensible `assert-expr` (in `phel\test`)
- **String utils**: `phel\string` with `upper-case`, `lower-case`, `split`, `join`, `trim`, `replace`, `starts-with?`, `ends-with?`, and more
- **Regex**: `re-find`, `re-matches`, `re-pattern`, `#"..."` literals

## Reader syntax

Clojure's reader syntax is accepted wholesale. Older Phel-specific forms still work but are deprecated in favour of the Clojure form:

| Syntax | Meaning | Old Phel form (deprecated) |
|--------|---------|----------------------------|
| `#(inc %)` | Anonymous fn shorthand | `\|(inc $)` |
| `~x`, `~@xs` | Unquote and unquote-splicing | `,x`, `,@xs` |
| `name#` | Auto-gensym suffix in syntax-quote | `name$` |
| `#'foo` | Var-quote (read as bare symbol `foo`) | |
| `\a`, `\space`, `\uNNNN`, `\oNNN` | Character literal (compiles to single-char PHP string) | |
| `##Inf`, `##-Inf`, `##NaN` | Symbolic numeric literal | |
| `2r1111`, `16rFF` | Radix literal (bases 2 to 36) | |
| `1N`, `1.5M` | BigInt / BigDecimal suffix (accepted, truncated to PHP int/float) | |
| `1/2`, `-3/4` | Ratio literal (accepted, evaluated as float division; `1/0` → `INF`, `0/0` → `NaN`) | |
| `#"regex"` | Regex literal | |
| `#?(...)`, `#?@(...)` | Reader conditionals (for `.cljc`) | |
| `#<tag> form` | Tagged literal dispatch | |

Notes:

- Named `fn` for self-recursion works: `(fn fact [n] (if (zero? n) 1 (* n (fact (dec n)))))`. Multi-arity named fns resolve the name across arities.
- `defmacro` bodies have access to `&form` (the original macro call) and `&env` (a map of locals at the call site), enabling dialect detection via `(:ns &env)` in `.cljc` sources.
- Phel has no first-class `Var` or `Char` type. `#'foo` compiles to a bare symbol reference, `\A` compiles to the single-character string `"A"`.
- Unknown `#<tag>` literals inside unselected `#?` branches parse without error, so `.cljc` files with foreign tags like `#cpp` in non-`:phel` branches work.

## Namespace syntax

Phel uses `\` as the native namespace separator (matching PHP), but accepts `.` for Clojure compatibility:

```phel
;; Both work:
(ns my\app (:require phel\string :as str))
(ns my.app (:require phel.string :as str))

;; Vector-style :require also works:
(ns my.app (:require [phel\string :as str :refer [upper-case]]))
```

**Automatic aliasing**: `clojure.*` namespaces in `:require` resolve to `phel.*` when the target exists, so `.cljc` files that `(:require [clojure.string :as str])` work without changes.

**Importing PHP classes**: use `(:use ...)` in the `ns` form to import a PHP class by short name, similar to PHP's `use` statement. This is Phel's equivalent of Clojure's `(:import ...)`:

```phel
(ns my\app
  (:use DateTime)
  (:use \JsonException)
  (:use Phel\Lang\Symbol))

(php/new DateTime)              ; short name works after :use
(php/:: Symbol (create "foo"))  ; FQN Phel\Lang\Symbol is aliased
```

`:use` is optional. Classes can always be referenced by fully-qualified name directly at the call site: `(php/new \DateTime)`.

## PHP interop (vs Java interop)

| Operation | Clojure (Java) | Phel (PHP) |
|-----------|---------------|------------|
| Instance method | `(.format date "Y-m-d")` | `(php/-> date (format "Y-m-d"))` |
| Static method | `(Math/abs -5)` | `(php/:: Math (abs -5))` |
| Constructor | `(java.util.Date.)` | `(php/new DateTime)` |
| Property read | `(.-length arr)` | `(php/-> arr -length)` |
| Property write | `(set! (.-x obj) 5)` | `(php/oset obj :x 5)` |
| Array access | `(aget arr 0)` | `(php/aget arr 0)` |
| Array write | `(aset arr 0 val)` | `(php/aset arr 0 val)` |
| Type check | `(instance? String x)` | `(instance? \String x)` or `(php/instanceof x \String)` |
| PHP function | N/A | `(php/strlen "hello")` |
| String concat | `(str a b)` | `(str a b)` or `(php/. a b)` |

Any PHP function can be called with the `php/` prefix:

```phel
(php/array_map f arr)
(php/strtolower "HELLO")
(php/json_encode {:a 1})
(php/date "Y-m-d")
```

## .cljc cross-compilation

Phel supports `.cljc` files with reader conditionals for sharing code between Clojure and Phel:

```clojure
(ns shared.utils
  (:require #?(:phel phel\string
               :clj  [clojure.string :as str])))

(defn greet [name]
  #?(:phel (str "Hello, " name "!")
     :clj  (str "Hello, " name "!")))
```

Platform tags: `:phel`, `:clj`, `:cljs`, `:default`. Phel selects `:phel` first, then `:default`.

Splice variant for embedding multiple forms:

```clojure
[1 2 #?@(:phel [3 4] :clj [5 6])]
;; In Phel: [1 2 3 4]
```

## Clojure-compatible renames

These Clojure names were added; the old Phel-specific names still work but are deprecated:

| Clojure name | Old Phel name |
|--------------|---------------|
| `atom`, `atom?` | `var`, `var?` |
| `reset!` | `set!` |
| `identical?` | `id` |
| `fn?` | `function?` |
| `map?` | `hash-map?` |
| `vals` | `values` |
| `with-meta` | `set-meta!` |
| `NaN?` | `nan?` |
| `integer?` | `int?` (alias, not deprecated) |

The deprecated names will be removed in a future major version.

## What's not available (and why)

Phel runs on PHP. A handful of Clojure features don't translate directly:

| Clojure feature | Why it's absent | Alternative |
|-----------------|----------------|-------------|
| **Refs / STM** | No concurrent transactions in PHP | Use `atom` for mutable state |
| **Agents** | No background threads | PHP job queues via interop |
| **core.async** | No goroutines/CSP | Use `phel\async` (fiber-based via AMPHP) |
| **BigInt / BigDecimal / Ratio** | PHP number model | Suffix literals (`1N`, `1.5M`) and ratio literals (`1/2`) are accepted; ratios evaluate to `num / den` as a float. Use `bcmath` / `gmp` via `php/` interop for real arbitrary precision |
| **Character type** | PHP has no char type | Character literals (`\a`) and `char` / `char?` are supported but compile to single-character strings |
| **Spec** | Not ported | Use runtime assertions or PHP validation |
| **Vars (Clojure sense)** | PHP has no thread-local bindings | `def` creates namespace-level bindings directly |
| **`alter-var-root`** | No first-class vars to re-root | Use an `atom` with `swap!` for mutable state, or redefine the top-level binding with `def`. Calling `alter-var-root` at runtime throws `BadMethodCallException` with this hint |

Phel does provide `future`, `future-cancel`, and `pmap` via the `phel\async` module, which uses AMPHP fibers. Semantics match Clojure's `future` where they can (including timeout-bounded `deref`), but cancellation is cooperative rather than thread-interrupt.

## Structural differences

### defstruct, defrecord, and deftype

Phel's native type form is `defstruct`. Clojure-compatible `defrecord` and `deftype` are thin macros over `defstruct` and produce the same `->Name` / `map->Name` factory functions Clojure programmers expect:

```phel
;; defstruct (native)
(defstruct Point [x y])
(let [p (Point 1 2)]
  (get p :x)) ;; => 1

;; defrecord (Clojure-compatible)
(defrecord Point [x y])
(->Point 1 2)            ;; positional factory
(map->Point {:x 1 :y 2}) ;; map factory

;; deftype
(deftype PointT [x y])
(->PointT 1 2)           ;; positional factory (no map-> counterpart)
```

Inline protocol methods work in both macro bodies and are spliced into an `extend-type` call:

```phel
(defprotocol Drawable
  (draw [this canvas]))

(defrecord Shape [label]
  Drawable
  (draw [this canvas] (str canvas ":" (get this :label))))
```

`reify` is also supported for anonymous protocol implementation:

```phel
(reify Drawable
  (draw [this canvas] (str canvas ":anon")))
```

Note: Phel's `deftype` shares the map-backed `defstruct` infrastructure, so instances remain map-like (keys accessible via `get`). Clojure's `deftype` creates a non-map type; if you need that, fall back to native PHP interop.

### No lazy-seq by default

Phel sequences are eager by default. Use `lazy-seq` explicitly when needed:

```phel
(defn lazy-fib
  ([] (lazy-fib 0 1))
  ([a b] (lazy-seq (cons a (lazy-fib b (+ a b))))))
```

`(range)` with 0 arguments returns an infinite lazy sequence (matching Clojure).

### Test framework

Phel's test framework lives in `phel\test` and mirrors `clojure.test`:

```phel
(ns my-app\test
  (:require phel\test :refer [deftest is testing are]))

(deftest test-addition
  (testing "basic math"
    (is (= 4 (+ 2 2)) "2 + 2 = 4")
    (are [x y z] (= z (+ x y))
      1 1 2
      2 2 4
      3 3 6)))
```

Extend `is` with custom assertion forms by adding a `phel\test/assert-expr` method:

```phel
(defmethod phel\test/assert-expr 'my-form [msg form] ...)
```

Run tests with `./bin/phel test`.

## Migration checklist

1. Rename `.clj` files to `.phel` (or `.cljc` for shared code)
2. Update namespace separators: `my.app.core` → `my\app\core` (or keep `.`, both work)
3. Replace Java interop with PHP interop (`(.method obj)` → `(php/-> obj (method))`)
4. Rewrite `(:import [java.util Date])` clauses as `(:use DateTime)` in the `ns` form
5. Check for concurrency primitives (refs, agents, core.async) and replace with `atom` or `phel\async` / AMPHP alternatives
6. Run `./bin/phel test` to verify

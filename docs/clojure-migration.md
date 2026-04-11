# Clojure to Phel Migration Guide

Phel is a functional Lisp inspired by Clojure that compiles to PHP. If you know Clojure, you already know most of Phel. This guide covers the differences.

## What's the same

The vast majority of Clojure's core library works identically in Phel:

- **Data structures**: persistent vectors `[]`, maps `{}`, sets (via `set`), sorted collections (`sorted-map`, `sorted-set`), lists `'()`
- **Core functions**: `map`, `filter`, `reduce`, `assoc`, `dissoc`, `get`, `get-in`, `update`, `update-in`, `conj`, `into`, `merge`, `select-keys`, `comp`, `partial`, `juxt`, `apply`, `first`, `rest`, `next`, `cons`, `nth`, `last`, `butlast`, `take`, `drop`, `partition`, `frequencies`, `group-by`, `sort-by`, `zipmap`, `str`, `keyword`, `symbol`, `name`, `namespace`, `some?`, `boolean`, `resolve`, `fnil`, `run!`, `rename-keys`, `min-key`, `max-key`, `update-keys`, `update-vals`, `parse-long`, `parse-double`, `random-uuid`, and many more
- **Macros**: `defmacro`, `defn`, `def`, `let`, `letfn`, `if`, `when`, `cond`, `case`, `condp`, `->`, `->>`, `as->`, `some->`, `some->>`, `cond->`, `cond->>`, `and`, `or`, `not`, `loop`/`recur`, `doseq`, `dotimes`, `for` (list comprehension), `if-some`, `when-some`, `when-first`, `assert`
- **Protocols and multimethods**: `defprotocol`, `extend-type`, `defmulti`, `defmethod`
- **Destructuring**: same syntax for vectors, maps, `:keys`, `:strs`, `:or`, `& rest`
- **Metadata**: `meta`, `with-meta`, `vary-meta`, `^:keyword` reader syntax
- **Atoms**: `atom`, `deref`/`@`, `swap!`, `reset!`, `add-watch`, `remove-watch`
- **Exceptions**: `ex-info`, `ex-data`, `ex-message`, `ex-cause`
- **Testing**: `deftest`, `is`, `testing`, `are`, `do-report`, extensible `assert-expr` (in `phel\test`)
- **Reader macros**: `'quote`, `` `syntax-quote ``, `~unquote`, `~@splice`, `#"regex"`, `#(...)` anonymous fn, `@deref`
- **Macro internals**: `&form` and `&env` implicit bindings in `defmacro` bodies, `name#` auto-gensym

## Quick reference

| Clojure | Phel | Notes |
|---------|------|-------|
| `(ns my.app (:require [clojure.string :as str]))` | `(ns my\app (:require phel\str :as str))` | `\` separator (`.` also works) |
| `(.method obj arg)` | `(php/-> obj (method arg))` | Instance method |
| `(Class/staticMethod arg)` | `(php/:: Class (method arg))` | Static method |
| `(ClassName. arg)` | `(php/new ClassName arg)` | Constructor |
| `(.-field obj)` | `(php/-> obj -field)` | Property access |
| `(:import [java.util Date])` | (not needed) | PHP classes available via `php/new` |
| `(instance? Type x)` | `(php/instanceof x Type)` | Type check (arg order differs) |
| `(class x)` | `(type x)` | Returns type keyword |
| `(subs s start end)` | `(phel\str\slice s start end)` | Substring |
| `(clojure.string/upper-case s)` | `(phel\str\upper-case s)` | String utils in `phel\str` |
| `(println "hi")` | `(println "hi")` | Same |
| `(throw (ex-info "msg" {:k v}))` | `(throw (ex-info "msg" {:k v}))` | Same |

## Namespace syntax

Phel uses `\` as the native namespace separator (matching PHP), but also accepts `.` for Clojure compatibility:

```phel
;; Both work:
(ns my\app (:require phel\str :as str))
(ns my.app (:require phel.str :as str))

;; Vector-style :require also works (Clojure style):
(ns my.app (:require [phel\str :as str :refer [upper-case]]))
```

**Automatic aliasing**: `clojure.*` namespaces in `:require` automatically resolve to `phel.*` when the target exists. This means `.cljc` files that `(:require [clojure.string :as str])` work without changes.

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
| Type check | `(instance? String x)` | `(php/instanceof x String)` |
| PHP function | N/A | `(php/strlen "hello")` |
| String concat | `(str a b)` | `(str a b)` or `(php/. a b)` |

### PHP function calls

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
  (:require #?(:phel phel\str
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

## Functions with different names

Most Clojure core functions have the same name in Phel. These are the exceptions:

| Clojure | Phel | Notes |
|---------|------|-------|
| `class` | `type` | Returns a keyword like `:string`, `:int`, `:hash-map` |
| `subs` | Use `phel\str\slice` | No direct `subs` in core |
| `re-pattern` | `re-pattern` | Same, or use `#"regex"` literal |
| `rand` | `rand` | Same |
| `rand-int` | `rand-int` | Same |
| `random-uuid` | `random-uuid` | Same |

### Recently aligned names

These Clojure-compatible names were added with deprecated Phel-specific aliases still available:

| Clojure name (now in Phel) | Old Phel name (deprecated) |
|----------------------------|---------------------------|
| `atom` | `var` |
| `atom?` | `var?` |
| `reset!` | `set!` |
| `identical?` | `id` |
| `fn?` | `function?` |
| `map?` | `hash-map?` |
| `vals` | `values` |
| `with-meta` | `set-meta!` |
| `integer?` | `int?` (alias, not deprecated) |

The deprecated names still work but will be removed in a future major version.

### Recently added Clojure-compatible functions

These functions and macros were added to match Clojure's standard library:

- **Collections**: `sorted-map`, `sorted-map-by`, `sorted-set`, `sorted-set-by`, `rename-keys`, `update-keys`, `update-vals`
- **Predicates**: `some?` (1-arg), `boolean`, `seq?`, `integer?`
- **Functions**: `fnil`, `run!`, `min-key`, `max-key`, `resolve`, `parse-long`, `parse-double`, `parse-boolean`, `abs`
- **Macros**: `letfn`, `dotimes`, `condp`, `if-some`, `when-some`, `when-first`, `assert`
- **Exceptions**: `ex-info`, `ex-data`, `ex-message`, `ex-cause`
- **Concurrency**: `delay`, `force`, `delay?`, `add-watch`, `remove-watch`
- **Transducers**: `transduce`, `into` (3-arg), `sequence`, `completing`, `cat`, plus transducer arities for `map`, `filter`, `take`, `drop`, `keep`, `distinct`, `dedupe`, `mapcat`, `interpose`, and more
- **Testing**: `testing`, `are`, `do-report`, extensible `assert-expr` multimethod

## Syntax parity

These Clojure syntax features are supported in Phel. Older Phel-specific alternatives are deprecated:

| Clojure syntax | Phel support | Old Phel syntax (deprecated) |
|----------------|-------------|------------------------------|
| `#(inc %)` anonymous fn | `#(inc %)` | `|(inc $)` |
| `~x` unquote | `~x` | `,x` |
| `~@xs` unquote-splicing | `~@xs` | `,@xs` |
| `name#` auto-gensym in syntax-quote | `name#` | `name$` |
| `&form` / `&env` in macros | Supported | N/A (new feature) |

### Macro internals

Like Clojure, `defmacro` bodies have access to two implicit bindings:

- `&form` — the original macro call form (for source location, metadata)
- `&env` — a map of locals in scope at the call site (useful for `.cljc` dialect detection via `(:ns &env)`)

## What's not available (and why)

Phel runs on PHP, which is single-threaded per request. This means several Clojure concurrency features don't apply:

| Clojure feature | Why it's absent | Alternative |
|-----------------|----------------|-------------|
| **Refs / STM** | No concurrent transactions in PHP | Use `atom` for mutable state |
| **Agents** | No background threads | Use PHP job queues via interop |
| **core.async** | No goroutines/CSP | Use PHP async libraries (ReactPHP, Amp) via interop |
| **Futures / promises** | No threads | Use PHP Fibers via interop |
| **BigInt / BigDecimal / Ratio** | PHP number model | Use `bcmath` or `gmp` extensions via `php/` interop |
| **Character type** | PHP has no char type | Use single-character strings |
| **Spec** | Not ported | Use runtime assertions or PHP validation |
| **Vars (Clojure sense)** | PHP has no thread-local bindings | `def` creates namespace-level bindings directly |

## Structural differences

### defstruct, defrecord, and deftype

Phel's native type form is `defstruct`. Clojure-compatible `defrecord` and
`deftype` are thin macros over `defstruct` and produce the same `->Name` /
`map->Name` factory functions Clojure programmers expect:

```phel
;; Phel — defstruct (native)
(defstruct Point [x y])
(let [p (Point 1 2)]
  (get p :x)) ;; => 1

;; Phel — defrecord (Clojure-compatible)
(defrecord Point [x y])
(->Point 1 2)           ;; positional factory
(map->Point {:x 1 :y 2}) ;; map factory

;; Phel — deftype
(deftype PointT [x y])
(->PointT 1 2)          ;; positional factory (no map-> counterpart)
```

Inline protocol method implementations work in both macro bodies and are
spliced into an `extend-type` call:

```phel
(defprotocol Drawable
  (draw [this canvas]))

(defrecord Shape [label]
  Drawable
  (draw [this canvas] (str canvas ":" (get this :label))))
```

Note: Phel's `deftype` still shares the map-backed `defstruct` infrastructure,
so instances remain map-like (keys are accessible via `get`). Clojure's
`deftype` creates a non-map type; if you need that semantic, fall back to
native PHP interop.

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
  (:require phel\test :refer [deftest is testing]))

(deftest test-addition
  (testing "basic math"
    (is (= 4 (+ 2 2)) "2 + 2 = 4")))
```

Run with: `./bin/phel test`

## Migration checklist

1. Rename `.clj` files to `.phel` (or `.cljc` for shared code)
2. Update namespace separators: `my.app.core` -> `my\app\core` (or keep `.` — both work)
3. Replace Java interop with PHP interop (`(.method obj)` -> `(php/-> obj (method))`)
4. Replace `defrecord` with `defstruct`
5. Replace `import` with direct `php/new` calls
6. Check for concurrency primitives (refs, agents, futures) and replace with PHP alternatives
7. Run `./bin/phel test` to verify

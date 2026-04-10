# Clojure to Phel Migration Guide

A reference for Clojure developers learning Phel. Most Clojure idioms translate directly; this guide covers the differences.

## What's the Same

The majority of `clojure.core` maps 1:1 to `phel\core`:

- Immutable data structures: vectors, hash-maps, sets, lists
- Core functions: `map`, `filter`, `reduce`, `comp`, `partial`, `juxt`, `apply`, `concat`, `conj`, `assoc`, `dissoc`, `get`, `get-in`, `update-in`, `merge`, `into`
- Sequence operations: `first`, `rest`, `next`, `take`, `drop`, `partition`, `interleave`, `flatten`
- Macros: `let`, `if`, `when`, `cond`, `case`, `->`, `->>`, `as->`, `and`, `or`, `defn`, `defmacro`, `loop`/`recur`
- Destructuring: vectors `[a b & rest]`, maps `{:keys [a b] :as m :or {a 1}}`
- Lazy sequences, transducers, `for` comprehensions
- `defstruct` for defining data types

## Quick Reference

### Mutable State: `atom` -> `var`

| Concept | Clojure | Phel |
|---------|---------|------|
| Create mutable ref | `(atom 0)` | `(var 0)` |
| Read | `@a` / `(deref a)` | `@a` / `(deref a)` |
| Update with fn | `(swap! a inc)` | `(swap! a inc)` |
| Reset | `(reset! a 5)` | `(set! a 5)` |

```phel
(def counter (var 0))
(swap! counter inc)    ;; => 1
(set! counter 10)      ;; => 10
(deref counter)        ;; => 10
```

### Namespace Syntax

| Aspect | Clojure | Phel |
|--------|---------|------|
| Separator | `.` | `\` (also accepts `.`) |
| Qualified call | `clojure.string/upper-case` | `phel\str/upper-case` |
| Namespace declaration | `(ns my.app (:require ...))` | `(ns my\app (:require ...))` |
| Require | `(:require [clojure.string :as s])` | `(:require phel\str :as s)` |
| Import class | `(:import [java.util Date])` | `(:use \DateTimeImmutable)` |

### PHP Interop (vs Java Interop)

| Operation | Clojure (Java) | Phel (PHP) |
|-----------|---------------|------------|
| Method call | `(.method obj arg)` | `(php/-> obj (method arg))` |
| Static call | `(Class/method arg)` | `(php/:: Class (method arg))` |
| New instance | `(ClassName. arg)` | `(php/new ClassName arg)` |
| Property read | `(.-field obj)` | `(php/oget obj :field)` |
| Property set | `(set! (.-field obj) v)` | `(php/oset obj :field v)` |
| Array access | `(aget arr i)` | `(php/aget arr i)` |
| instanceof | `(instance? Class x)` | `(php/instanceof x Class)` |

```phel
;; Create and use PHP objects
(let [date (php/new \DateTimeImmutable "2024-01-15")]
  (php/-> date (format "Y-m-d")))   ;; => "2024-01-15"

;; Static methods
(php/:: \DateTimeImmutable (createFromFormat "Y" "2024"))

;; Global PHP functions
(php/strtoupper "hello")   ;; => "HELLO"
```

### Functions with Different Names

| Clojure | Phel | Notes |
|---------|------|-------|
| `identical?` | `id` | Reference equality |
| `class` | `type` | Returns type keyword |
| `subs` | `phel\str/subs` | Substring |
| `clojure.string/*` | `phel\str/*` | String utilities |
| `clojure.test/*` | `phel\test/*` | Test framework |
| `println` | `println` | Same, prints to stdout |
| `str` | `str` | Same, concatenation |

### Structural Differences

| Feature | Clojure | Phel |
|---------|---------|------|
| Multi-arity `defn` | `(defn f ([x] ...) ([x y] ...))` | Supported (same syntax as Clojure) |
| Protocols | `defprotocol` + `defrecord` | `definterface` + PHP classes |
| Metadata on locals | `(with-meta x m)` | `(set-meta! x m)` / `(vary-meta x f)` |
| Reader conditionals | `#?(:clj ... :cljs ...)` | `#?(:phel ... :default ...)` |
| Regex literals | `#"pattern"` | `(php/preg_match "pattern" str)` |

## Namespace Mapping

| Clojure | Phel | Require syntax |
|---------|------|----------------|
| `clojure.core` | `phel\core` | Auto-loaded |
| `clojure.string` | `phel\str` | `(:require phel\str)` |
| `clojure.test` | `phel\test` | `(:require phel\test)` |
| `clojure.walk` | `phel\walk` | `(:require phel\walk)` |
| `clojure.set` | N/A | Use `phel\core` set functions |

## Features Not Available (and Why)

| Clojure Feature | Why Not in Phel |
|----------------|-----------------|
| STM / refs / agents | PHP is single-threaded per request; no shared-memory concurrency |
| core.async | No goroutines; use PHP async libraries (Amp, ReactPHP) via interop |
| BigInt / Ratio | PHP number limits; use `bcmath` or `gmp` extensions via interop |
| Character type | PHP has no char type; use single-character strings |
| Spec / Schema | Use PHP validation libraries via interop |

## Testing

Phel's test framework mirrors `clojure.test`:

```phel
(ns my-app\test
  (:require phel\test :refer [deftest is testing]))

(deftest test-addition
  (testing "basic math"
    (is (= 4 (+ 2 2)) "two plus two equals four")
    (is (thrown? \InvalidArgumentException
                (throw (php/new \InvalidArgumentException "boom"))))))
```

Run with: `./vendor/bin/phel test`

## Common Patterns

### Error Handling

```phel
;; Clojure: (try ... (catch Exception e ...))
;; Phel:    Same syntax, but use PHP exception classes
(try
  (risky-operation)
  (catch \RuntimeException e
    (println "Error:" (php/-> e (getMessage)))))
```

### Working with PHP Arrays

```phel
;; PHP arrays are NOT Phel data structures.
;; Convert with `to-php-array` / `php-array-to-map`:
(let [php-arr (php/array "a" "b" "c")]
  (php/count php-arr))  ;; => 3
```

### REPL Usage

```bash
./vendor/bin/phel repl
```

```phel
phel:1> (require 'phel\str)
phel:2> (phel\str/upper-case "hello")
"HELLO"
```

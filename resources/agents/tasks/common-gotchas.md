# Common gotchas

Pitfalls hit during real-world Phel app development. Read before writing your first app. Rules in [`RULES.md`](../RULES.md); this file adds the example each rule's one-liner doesn't show.

## 1. CLI argument access

`php/$argv` is `null` inside `phel run`. Use `*argv*`:

```phel
(let [args *argv*]                ; Phel vector of strings after the script path
  (println "First arg:" (first args)))
```

Full CLI: `tasks/cli-tool.md`.

## 2. `transduce` with `max` / `min`

`max` and `min` lack a 0-arity init, so pass an explicit seed. A hand-written reducer also needs a 1-arity, because `transduce` calls it once to complete; `completing` adds one:

```phel
;; bad
(transduce (map :score) max items)
(transduce (map :score) (fn [a b] (max a b)) 0 items)
;; good
(transduce (map :score) max 0 items)
(transduce (map :score) (completing (fn [a b] (max a b))) 0 items)
```

## 3. `for` vs `doseq`

`for` builds a lazy sequence; `doseq` runs for side effects.

```phel
(doseq [t :in todos] (println t))            ; side effects
(for   [x :in xs :when (odd? x)] (* x x))    ; build a collection
```

## 4. Top-level side effects break `phel build`

Top-level code runs at compile time. Guard:

```phel
(when-not *build-mode*
  (start-server))
```

## 5. PHP arrays vs Phel collections

PHP interop returns raw PHP arrays, not Phel collections. Convert:

```phel
(vec (php/explode "," "a,b,c"))   ; PHP array  → Phel vector
(to-array ["a" "b" "c"])          ; Phel vec   → PHP indexed array
(phel->php {:a 1})                ; Phel map   → PHP assoc array
(php-array-to-map arr)            ; PHP assoc  → Phel map
```

`to-list` and `to-vec` don't exist. Use `vec` / `to-array`.

## 6. Record fields use `get`, not `.-prop`

```phel
(defrecord Point [x y])
(let [p (->Point 1 2)]
  (get p :x))                     ; not (.-x p) — that's PHP property access
```

## 7. `:tag` literal mismatch is a compile error

```phel
(defn ^int square [^int x] (* x x))
(square "abc")                    ; :phel/static-type at compile time, not runtime
```

Same for `recur` args vs binding tags and tail literal vs declared return tag. See `tasks/typed-defn.md`.

## 8. `^` tags one symbol; map form for unusual types

```phel
(defn parse [^"?int" s] ...)              ; quote the type string
(defn parse [^{:tag "?int"} s] ...)       ; map form
(defn now   [^DateTimeImmutable] ...)        ; root classes need no marker
```

`^?int` parses as a symbol named `?int`, not a nullable-int tag.

## 9. A `php/` call is not always a missing wrapper

Prefer the core fn when one exists, but a raw `php/` call in `phel.core` is
sometimes the *correct* spelling because the semantics differ, exactly as
Clojure's own core keeps `(.length s)` and `Math/floor` where a wrapper would
lie:

```phel
(int 1e30)                 ; throws, like Clojure's (int 1e30)
(php/intval 1e30)          ; coerces to garbage; only right when you want lossy
(phel.string/replace s "." "-") ; regex, like clojure.string/replace
(php/str_replace "." "-" s)     ; literal, and much faster for a literal
```

Reach for the wrapper first; keep the `php/` call when its behaviour is the
one you actually need. See #2941.

## 10. Clojure habits that do not work

Phel runs on PHP, not the JVM. A Java class name resolves as a PHP class and fails with `Class "Math" not found`.

| Clojure | Phel |
|---------|------|
| `(Integer/parseInt s)`, `(Long/parseLong s)` | `(parse-long s)` |
| `(Double/parseDouble s)` | `(parse-double s)` |
| `(Math/abs x)` | `(abs x)` |
| `(Thread/sleep ms)` | `(php/usleep (* ms 1000))` |
| `(System/currentTimeMillis)` | `(php/intval (* 1000 (php/microtime true)))`; `(php/microtime true)` alone is seconds as a float |
| `(.toUpperCase s)`, any method on a string | `(phel.string/upper-case s)`; a PHP string has no methods |
| `(:require [x :refer :all])` | `:refer [a b]` with each name, or `:as x` |
| `(:import (java.time Instant))` | `(:use DateTimeImmutable)` for a PHP class |
| `phel.str` | `phel.string` |
| `clojure.set` | `union`, `intersection`, `difference` are in `phel.core` |
| `(require ...)` at the top of a file | `(:require ...)` inside `ns`; `require` only exists in the REPL |

`clojure.string` maps to `phel.string`, so `(:require clojure.string :as str)` works.

## See also

- [`RULES.md`](../RULES.md) for the one-liner rule each gotcha references.
- [`tasks/debug-errors.md`](debug-errors.md) — error categories and fixes.
- [`tasks/typed-defn.md`](typed-defn.md) — typing rules in depth.

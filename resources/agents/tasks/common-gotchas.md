# Common gotchas

Pitfalls encountered during real-world Phel app development. Read before writing your first app.

## 1. CLI argument access

**Wrong** — `php/$argv` is `null` inside `phel run`:

```phel
;; DON'T: this fails at runtime
(let [args php/$argv] ...)
```

**Right** — use `*argv*`, which returns a Phel vector of strings (args after the script path):

```phel
(let [args *argv*]
  (println "First arg:" (first args)))
```

For the full `phel\cli` module (subcommands, options, prompts), see `tasks/cli-tool.md`.

## 2. `transduce` with `max` / `min`

`max` and `min` don't support 0-arity (no identity value), so they can't be used as bare reducing functions with `transduce`:

```phel
;; DON'T: fails — max needs 2 args, transduce calls (max) for init
(transduce (map :score) max items)

;; DO: wrap and provide init
(transduce (map :score) (fn [a b] (max a b)) 0 items)
```

## 3. `for` vs `doseq`

`for` is a **list comprehension** — it builds a lazy sequence. `doseq` is for **side effects**.

```phel
;; DON'T: for returns a sequence, side effects are incidental
(for [t :in todos] (println t))

;; DO: doseq when you want side effects
(doseq [t :in todos] (println t))

;; DO: for when you want to build a collection
(for [x :in xs :when (odd? x)] (* x x))
```

## 4. `phel\string` (not `phel\str`)

Since v0.33, the string module is `phel\string`:

```phel
;; DON'T
(:require phel\str :as str)

;; DO
(:require phel\string :as string)
```

## 5. Namespace segment count

Namespaces need at least 2 segments. Single-segment namespaces cause a compile error:

```phel
;; DON'T
(ns main)

;; DO
(ns my-app\main)
```

## 6. Top-level side effects and `phel build`

Code at the top level runs during compilation. Wrap side effects:

```phel
;; DON'T: breaks phel build
(println "starting...")
(start-server)

;; DO
(when-not *build-mode*
  (println "starting...")
  (start-server))
```

## 7. Converting PHP arrays to Phel collections

PHP arrays from interop are not Phel collections. Convert them:

```phel
;; PHP array → Phel vector
(vec (php/explode "," "a,b,c"))

;; Phel collection → PHP array (for passing to PHP functions)
(to-php-array ["a" "b" "c"])
```

Don't try `to-list` (doesn't exist) or `to-vec` (doesn't exist). Use `vec`.

## 8. Record fields are accessed with `get`

`defrecord` fields use keyword access via `get`, not dot notation:

```phel
(defrecord Point [x y])
(let [p (->Point 1 2)]
  ;; DON'T: (.-x p) — this is PHP property access
  ;; DO:
  (get p :x))
```

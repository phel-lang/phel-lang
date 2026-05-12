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

`max` and `min` lack a 0-arity init. Wrap and pass an explicit seed:

```phel
;; bad
(transduce (map :score) max items)
;; good
(transduce (map :score) (fn [a b] (max a b)) 0 items)
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
(to-php-array ["a" "b" "c"])      ; Phel vec   → PHP indexed array
(php-array-to-map arr)            ; PHP assoc  → Phel map
```

`to-list` and `to-vec` don't exist. Use `vec` / `to-php-array`.

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
(defn now   [^"\\DateTimeImmutable"] ...) ; class FQN needs leading \\
```

`^?int` parses as a symbol named `?int`, not a nullable-int tag.

## See also

- [`RULES.md`](../RULES.md) for the one-liner rule each gotcha references.
- [`tasks/debug-errors.md`](debug-errors.md) — error categories and fixes.
- [`tasks/typed-defn.md`](typed-defn.md) — typing rules in depth.

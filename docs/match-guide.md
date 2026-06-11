# Pattern Matching Guide

`phel.match` provides a `match` macro that destructures by shape. Expands to nested `cond` + `let` at compile time.

## Quickstart

```phel
(ns my-app.main
  (:require phel.match :refer [match]))

(defn describe [x]
  (match [x]
    [0]                   "zero"
    [[a b]]               (str "pair " a " / " b)
    [{:type :err :msg m}] (str "error: " m)
    [(n :guard pos?)]     "positive"
    :else                 "other"))
```

## Pattern kinds

| Pattern | Meaning |
|---------|---------|
| `42`, `:key`, `"s"` | literal equality |
| `_` | wildcard |
| `sym` | bind target to `sym` |
| `[a b c]` | vector of exactly 3 elements |
| `{:k sym}` | map with key `:k`, bind value to `sym` |
| `(:or a b c)` | any of the alternatives |
| `(sym :as name)` | bind whole subject to `name` |
| `(sym :guard pred)` | bind plus runtime predicate |

## Guards

```phel
(match [n]
  [(x :guard neg?)] "negative"
  [(x :guard pos?)] "positive"
  :else "zero")
```

## Rest binding

```phel
(match [xs]
  [[head & tail]] (str head ":" (count tail)))
```

## Pitfalls

- Each pattern vector length must equal the target count
- `:else` must be the final clause
- Nested patterns bind left-to-right; a later binding shadows an earlier one with the same name
- A `:guard` predicate runs against the raw value. Numeric predicates like `pos?` coerce non-numbers (`(pos? [1 2])` is truthy), so put literal/structural patterns before an open numeric guard.

## See also

- `phel.schema`: shapes reusable across validation and matching
- `cond`, `case`, `condp`: simpler dispatch without destructuring

---

📖 **Full guide:** [match API on phel-lang.org](https://phel-lang.org/documentation/reference/api/match/)

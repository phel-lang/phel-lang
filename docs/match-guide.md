# Pattern Matching Guide

`phel\match` provides a `match` macro that destructures by shape. It expands to nested `cond` + `let` at compile time.

## Contents

- [Quickstart](#quickstart)
- [Pattern kinds](#pattern-kinds)
- [Guards](#guards)
- [Rest binding](#rest-binding)
- [Pitfalls](#pitfalls)

## Quickstart

```phel
(ns my-app\main
  (:require phel\match :refer [match]))

(defn describe [x]
  (match [x]
    [0]            "zero"
    [(_ :guard pos?)] "positive"
    [[a b]]        (str "pair " a " / " b)
    [{:type :err :msg m}] (str "error: " m)
    :else          "other"))
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
| `(sym :guard pred)` | literal plus runtime predicate |

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
- Nested patterns bind in left-to-right order; a later binding shadows an earlier one with the same name

## See also

- `phel\schema`: shapes reusable across validation and matching
- `cond`, `case`, `condp`: simpler dispatch without destructuring

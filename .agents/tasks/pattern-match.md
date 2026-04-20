# Pattern match

`phel\match/match` destructures by shape. It expands to nested `cond` + `let` at compile time.

## Syntax

```phel
(match [target1 target2 ...]
  pattern1 expr1
  pattern2 expr2
  :else    default)
```

Each pattern is a vector whose length must equal the target count.

## Basic example

```phel
(ns my-app\main
  (:require phel\match :refer [match]))

(defn describe [x]
  (match [x]
    [0]               "zero"
    [(n :guard pos?)] "positive"
    [[a b]]           (str "pair " a "/" b)
    [{:type :err :msg m}] (str "error: " m)
    :else             "other"))
```

## Pattern kinds

| Pattern | Meaning |
|---------|---------|
| `42`, `:k`, `"s"` | literal equality |
| `_` | wildcard |
| `sym` | bind target to `sym` |
| `[a b c]` | vector of exactly 3 |
| `{:k sym}` | map with `:k`, bind value to `sym` |
| `(:or a b)` | alternative match |
| `(sym :as name)` | bind the whole subject |
| `(sym :guard pred)` | runtime predicate check |
| `[head & tail]` | rest binding |

## Gotchas

- Pattern vector length must equal target count
- `:else` must be last
- Later bindings shadow earlier ones with the same name

## See also

- `validate-with-schema.md` for declaring reusable shapes
- `docs/match-guide.md` for full reference

---
description: Phel language idioms, core library patterns, and testing conventions. Auto-loads when working on Phel source files in src/phel/ or tests/phel/.
user-invocable: false
---

# Phel Patterns

## Core Library Structure (`src/phel/`)

| Module | Purpose |
|--------|---------|
| `core.phel` | Fundamental functions (map, filter, reduce, etc.) |
| `str.phel` | String manipulation |
| `html.phel` | HTML generation |
| `http.phel` | HTTP request/response |
| `json.phel` | JSON encoding/decoding |
| `test.phel` | Test framework (deftest, is, are) |
| `pprint.phel` | Pretty printing |
| `walk.phel` | Tree walking (postwalk, prewalk) |
| `mock.phel` | Mocking utilities for tests |
| `debug.phel` | Debugging utilities |
| `repl.phel` | REPL functionality |
| `base64.phel` | Base64 encoding/decoding |

## Common Idioms

```phel
# Function definition with metadata
(defn my-fn
  {:doc "Description of what it does"
   :see-also ["related-fn" "other-fn"]
   :example "(my-fn arg) => result"}
  [arg]
  (body))

# Private function (not exported)
(defn- helper-fn [x] ...)

# Struct definition
(defstruct my-struct [field-a field-b])

# Threading macros
(-> value (fn1 arg) (fn2 arg))   # thread-first
(->> value (fn1 arg) (fn2 arg))  # thread-last
```

## Testing Patterns

```phel
(ns phel-test\test\module-name
  (:require phel\test :refer [deftest is are]))

(deftest test-descriptive-name
  (is (= expected (function-under-test input)))
  (is (thrown? \Exception (function-that-throws))))
```

Run: `./bin/phel test` (all) or `./bin/phel test tests/phel/<file>` (specific)

## Conventions

- `conj` over `put` for collections
- Keyword arguments via maps: `(fn opts)` not positional args
- Destructuring in `let` and `fn` parameter lists
- Prefer pure functions, isolate side effects

---
name: phel-patterns
description: Phel language idioms and core-library conventions. Use when Codex edits src/phel, tests/phel, Phel functions, Phel metadata, Phel tests, or core library behavior.
---

# Phel Patterns

## Core Library

| Module | Purpose |
|--------|---------|
| `core.phel` | Fundamental functions |
| `str.phel` | String manipulation |
| `html.phel` | HTML generation |
| `http.phel` | HTTP request and response helpers |
| `json.phel` | JSON encoding and decoding |
| `test.phel` | Test framework |
| `pprint.phel` | Pretty printing |
| `walk.phel` | Tree walking |
| `mock.phel` | Mocking utilities |
| `debug.phel` | Debugging utilities |
| `repl.phel` | REPL functionality |
| `base64.phel` | Base64 helpers |

## Idioms

```phel
;; Public function with metadata
(defn my-fn
  {:doc "Description of what it does"
   :see-also ["related-fn" "other-fn"]
   :example "(my-fn arg) => result"}
  [arg]
  (body))

;; Private function
(defn- helper-fn [x] ...)

(defstruct my-struct [field-a field-b])

(-> value (fn1 arg) (fn2 arg))
(->> value (fn1 arg) (fn2 arg))
```

## Tests

```phel
(ns phel-test\test\module-name
  (:require phel\test :refer [deftest is are]))

(deftest test-descriptive-name
  (is (= expected (function-under-test input)))
  (is (thrown? \Exception (function-that-throws))))
```

Run all Phel tests with:

```bash
./bin/phel test
```

Run a specific file with:

```bash
./bin/phel test tests/phel/<file>
```

## Conventions

- Use `;` comments, with `;;` for standalone comments.
- Use kebab-case for functions and variables.
- Prefer `conj` over `put` for collections.
- Prefer keyword arguments via maps over positional option lists.
- Use destructuring in `let` and function parameter lists when it improves clarity.
- Prefer pure functions and isolate side effects.

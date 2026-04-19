# Task: Add tests in Phel

## Goal

Write and run tests for Phel code with `phel\test`: `deftest`, `is`, `are`, `testing`, fixtures. Fast feedback via REPL.

## Prereqs

- Project scaffolded (see `.agents/tasks/scaffold-project.md`)
- `./vendor/bin/phel` works

## File layout

| Project layout | Source | Test |
|----------------|--------|------|
| default | `src/phel/<name>.phel` | `tests/phel/<name>_test.phel` |
| `--flat` | `src/<name>.phel` | `tests/<name>_test.phel` |
| `--minimal` | `<name>.phel` | `<name>_test.phel` |

Test namespace convention: `tests\<name>-test` (kebab-case suffix, matches file path under the configured test dir).

## Steps

### 1. Minimal example: `math` + `math-test`

`src/phel/math.phel`:

```phel
(ns my-app\math)

(defn add [a b]
  (+ a b))

(defn divide [a b]
  (when (zero? b)
    (throw (php/new \InvalidArgumentException "division by zero")))
  (/ a b))
```

`tests/phel/math_test.phel`:

```phel
(ns tests\math-test
  (:require phel\test :refer [deftest is are testing])
  (:require my-app\math :refer [add divide]))

(deftest test-add
  (is (= 4 (add 2 2)))
  (is (= 0 (add -1 1))))

(deftest test-add-table
  (are [x y sum] (= sum (add x y))
    0 0 0
    1 2 3
    -1 1 0))

(deftest test-divide-rejects-zero
  (testing "guards divide by zero"
    (is (thrown? \InvalidArgumentException (divide 1 0)))
    (is (thrown-with-msg? \InvalidArgumentException "division by zero"
          (divide 1 0)))))
```

### 2. Run tests

```bash
./vendor/bin/phel test                        # all tests under tests/
./vendor/bin/phel test tests/phel/math_test.phel   # one file
./vendor/bin/phel test --filter=test-add      # by test name substring
./vendor/bin/phel test --testdox              # readable per-test output
./vendor/bin/phel test --fail-fast            # stop on first failure
```

Full flag list: `./vendor/bin/phel test --help`.

### 3. REPL-driven TDD loop

```phel
phel:1> (require 'tests\math-test :reload)   ; reload after edits
phel:2> (tests\math-test/test-add)           ; run one deftest fn directly
phel:3> (require 'phel\test :refer [run-tests])
phel:4> (run-tests {} 'tests\math-test)      ; run the whole ns
```

Each `deftest` compiles to a zero-arg fn tagged `{:test true}`, so calling it directly re-runs only that test.

## Assertion forms inside `is`

| Form | What it asserts |
|------|-----------------|
| `(is (= expected actual))` | binary equality — reports both sides on failure |
| `(is (pred x))` | unary predicate — `pos?`, `nil?`, `string?`, etc. |
| `(is expr)` | truthy fallback (`:any`) for anything else |
| `(is (not form))` | negated binary / predicate / any |
| `(is (thrown? ExceptionClass body...))` | body throws `ExceptionClass` (or subclass) |
| `(is (thrown? body...))` | body throws anything (`\Throwable`) |
| `(is (thrown-with-msg? ExceptionClass "msg" body...))` | throws with exact message |
| `(is (output? "expected" body))` | body prints exactly `"expected"` to stdout |
| `(is form "message")` | optional message shown on failure |

## `are` — table-driven assertions

`argv` is the template vars; trailing args are consumed in groups of `(count argv)` and substituted at macro-expansion time:

```phel
(are [x y] (= x y)
  2 (+ 1 1)
  4 (* 2 2))
```

Incomplete trailing groups are dropped silently. Literal `()`/`[]`/`{}` cells are preserved as data, not evaluated as calls.

## Fixtures: setup / teardown

```phel
(ns tests\db-test
  (:require phel\test :refer [deftest is use-fixtures]))

(def conn (atom nil))

(use-fixtures :once
  (fn [t]
    (reset! conn (open-connection))
    (t)
    (close-connection @conn)))

(use-fixtures :each
  (fn [t]
    (clear-tables @conn)
    (t)))

(deftest test-insert
  (is (= 1 (insert @conn {:name "Alice"}))))
```

- `:once` — wraps the whole namespace run, fires once
- `:each` — wraps every `deftest` individually
- Multiple fixtures of the same kind compose outer-to-inner in registration order
- `(use-fixtures :each)` with no fns clears fixtures of that type for the namespace

## Extending `is` with custom assertions

`phel\test/assert-expr` is a public multimethod dispatched on the first symbol of the form inside `is`. Register a `defmethod` that returns the code the outer `is` should expand to:

```phel
(ns tests\math-test
  (:require phel\test :refer [deftest is]))

(defmethod phel\test/assert-expr 'approx= [form message]
  (let [a (second form)
        b (second (next form))
        epsilon 0.001]
    `(is (< (php/abs (- ~a ~b)) ~epsilon) ~message)))

(deftest test-approx
  (is (approx= 1.0 1.0001)))
```

Qualify the multi as `phel\test/assert-expr` when registering from another namespace, otherwise the method lands in the wrong dispatch table. See `docs/patterns.md` § Writing Macros and `tests/phel/test/assert-expr-extensibility.phel` for a worked set.

## Mocking

Full reference: `docs/mocking-guide.md`. Five-line version:

```phel
(ns tests\user-test
  (:require phel\test :refer [deftest is])
  (:require phel\mock :refer [with-mocks mock called-with?])
  (:require my-app\users :refer [fetch-user]))

(deftest test-fetch-user-hits-endpoint
  (with-mocks [http-get (mock {:id 1 :name "Alice"})]
    (fetch-user 123)
    (is (called-with? http-get "/users/123"))))
```

`with-mocks` auto-resets bindings after the body. Use `mock-fn`, `mock-returning`, `mock-throwing`, or `spy` for other patterns.

## Gotchas

- Test file name must end `_test.phel`; namespace must end `-test`. The runner discovers fns tagged `:test true`, but `init` and the published convention use this suffix.
- Don't forget `:refer [deftest is]` — bare `phel\test` import won't bring them into scope.
- `deftest` requires a symbol name: `(deftest test-foo ...)`, not a string.
- `(thrown? body)` catches any `\Throwable`. Use `(thrown? \MyException body)` to be specific.
- Fixtures are keyed by `*ns*` at `use-fixtures` time. Registering from another namespace won't wrap the target ns's tests.
- Custom `assert-expr` methods must be loaded before any `is` form uses their dispatch symbol — put them at the top of the test file or require a helper ns first.
- `are` drops trailing groups that don't fill `argv`. If you expect N assertions and see N-1, count your rows.

## Next

- `docs/quickstart.md` § Testing Your Code — narrative intro
- `docs/mocking-guide.md` — full mock / spy reference
- `docs/patterns.md` § Writing Macros — gensym, `&form`, `&env` for custom assertions
- `src/phel/test.phel` — public API source of truth
- `tests/phel/test/*.phel` — real-world coverage of every feature above

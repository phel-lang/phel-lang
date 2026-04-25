# Add tests

`phel\test`: `deftest`, `is`, `are`, `testing`, fixtures.

## Layout

| Layout | Source | Test |
|--------|--------|------|
| flat (default) | `src/<name>.phel` | `tests/<name>_test.phel` |
| `--nested` | `src/phel/<name>.phel` | `tests/phel/<name>_test.phel` |
| `--minimal` | `<name>.phel` | `<name>_test.phel` |

Namespace: `tests\<name>-test`.

## Example

`src/math.phel`:

```phel
(ns my-app\math)

(defn add [a b] (+ a b))

(defn divide [a b]
  (when (zero? b)
    (throw (php/new \InvalidArgumentException "division by zero")))
  (/ a b))
```

`tests/math_test.phel`:

```phel
(ns tests\math-test
  (:require phel\test :refer [deftest is are testing])
  (:require my-app\math :refer [add divide]))

(deftest test-add
  (is (= 4 (add 2 2))))

(deftest test-add-table
  (are [x y sum] (= sum (add x y))
    0 0 0
    1 2 3))

(deftest test-divide-rejects-zero
  (is (thrown-with-msg? \InvalidArgumentException "division by zero"
        (divide 1 0))))
```

## Run

```bash
./vendor/bin/phel test                       # all
./vendor/bin/phel test tests/math_test.phel  # one file
./vendor/bin/phel test --filter=test-add     # by name substring
./vendor/bin/phel test --testdox
./vendor/bin/phel test --fail-fast
```

## Assertion forms (inside `is`)

| Form | Asserts |
|------|---------|
| `(= expected actual)` | equality; reports both sides |
| `(pred x)` | unary predicate |
| `expr` | truthy fallback |
| `(not form)` | negation |
| `(thrown? Class body)` | body throws `Class` |
| `(thrown-with-msg? Class "msg" body)` | message match |
| `(output? "text" body)` | stdout match |

## Fixtures

```phel
(use-fixtures :once  (fn [t] (open-db)  (t) (close-db)))
(use-fixtures :each  (fn [t] (clear-tables) (t)))
```

Compose outer-to-inner. Keyed by `*ns*`.

## Mocking

```phel
(:require phel\mock :refer [with-mocks mock mock-fn mock-returning mock-throwing spy
                            called? called-with? called-once? never-called?
                            call-count calls reset-mock!])

(deftest hits-endpoint
  (with-mocks [http-get (mock {:id 1})]
    (fetch-user 123)
    (is (called-with? http-get "/users/123"))
    (is (called-once? http-get))))
```

Helpers: `mock` (fixed return), `mock-fn` (custom behavior), `mock-returning` (sequence), `mock-throwing` (error), `spy` (wrap real fn).

Full: `docs/mocking-guide.md`.

## Extend `is`

`phel\test/assert-expr` is an open multimethod. Register a `defmethod` to teach `is` a new form. See `docs/patterns.md` § Writing Macros.

## Gotchas

- File `_test.phel`, namespace `-test`.
- `:refer [deftest is]` required.
- `(thrown? body)` catches any `\Throwable`; specify class to target.
- No `:reload`. Restart REPL or run `phel test`.

## Next

`src/phel/test.phel`, `docs/mocking-guide.md`, `tests/phel/test/*.phel`

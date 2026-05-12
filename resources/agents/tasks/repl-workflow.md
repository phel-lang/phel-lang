# REPL workflow

```bash
./vendor/bin/phel repl
```

Exit: `(exit)` or Ctrl-D. No hot-reload — restart after source edits or run `phel test`.

## Load + switch ns

```phel
(require 'my-app\core)
(in-ns 'my-app\core)
```

## Inspect

```phel
(doc map)
(resolve 'map)
(type {:a 1})
```

Shell: `./vendor/bin/phel doc <fn>`.

## Expand macros

```phel
(macroexpand-1 '(when x y))
(macroexpand   '(-> x f g))
```

## Run tests

```phel
(require 'phel\test :refer [run-tests])
(require 'tests\math-test)
(run-tests {} 'tests\math-test)
(tests\math-test/test-add)
```

Each `deftest` is a zero-arg fn tagged `{:test true}`.

Shell: `./vendor/bin/phel test [path] [--filter=substring] [--testdox] [--fail-fast]`.

## State

```phel
(def c (atom 0))  (swap! c inc)  @c
(require 'phel\pprint :refer [pprint])  (pprint x)
```

## Errors

Errors print trace and keep REPL alive. `(try expr (catch php\Foo e (.getMessage e)))`.

## Gotchas

- No `:reload`. Restart after source edits.
- `require` of a throwing file crashes REPL.
- `in-ns` on missing ns creates empty one. `require` first.
- `(def map ...)` shadows core until restart.

## Profile from CLI

```bash
./vendor/bin/phel profile src/main.phel             # text table
./vendor/bin/phel profile src/main.phel --format=json --output=profile.json
```

Per-fn call counts and self/total/avg/max plus compile-phase costs. Use to pick fns worth tagging (`tasks/typed-defn.md`).

## See also

- `tasks/use-core-lib.md`, `tasks/debug-errors.md`, `tasks/typed-defn.md`
- `src/phel/repl.phel`, `docs/nrepl-guide.md`

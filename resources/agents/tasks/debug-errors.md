# Debug errors

Errors print `file:line:col` when available. Re-read the snippet there.

## Check loop

Run it after every edit, and first when something fails:

```bash
./vendor/bin/phel lint src/main.phel --format=json   # every error and warning, with line and column
./vendor/bin/phel explain PHEL001                    # what a PHELnnn code means and how to fix it
./vendor/bin/phel doc partition --format=json        # signatures and doc of the fn you are calling
```

`./vendor/bin/phel analyze <file>` prints the analyzer's diagnostics alone, as JSON. `./vendor/bin/phel compile '<expr>'` prints the PHP a form compiles to, which settles most interop questions.

## Categories

| Phase | Typical shape |
|-------|---------------|
| Lexer | Unbalanced paren, unterminated string |
| Reader | Invalid literal, bad `#reader` form |
| Analyzer | "Cannot resolve symbol", arity mismatch, bad special form, `:tag` mismatch |
| Emitter | Rare; compiler bug, report upstream |
| Runtime | PHP exception, nil method call, untyped `recur` arity mismatch |

## Common fixes

### "Cannot resolve symbol X"

Missing `:require`/`:use`, typo, shadowed core fn, wrong `:as`/`:refer`. A Clojure name that Phel spells differently (`Math/abs`, `Integer/parseInt`) also lands here or fails with `Class "Math" not found`: see `tasks/common-gotchas.md`. `require` itself only exists in the REPL; in a file, write `(:require ...)` inside `ns`.

### "Cannot find namespace"

File path must match ns:

| Layout | ns | File |
|--------|----|----|
| flat | `my-app.main` | `src/main.phel` |
| flat | `my-app.users.repo` | `src/users/repo.phel` |
| nested | `my-app.main` | `src/phel/main.phel` |
| root | `my-app.main` | `main.phel` (what `phel init --minimal` writes) |

Namespaces need ≥ 2 segments.

### `phel build` hangs

Top-level side effect. Guard: `(when-not *build-mode* (main))`.

### `(conj v x)` didn't change `v`

Immutability. Rebind: `(def v (conj v x))`, or use atom.

### Arity mismatch

Check `(doc fn)`. Variadic: `[a b & rest]`.

### "Not a function" / "is not callable"

- Unresolved symbol
- Calling a value: `(5 1 2)`
- Missing parens: `(map f xs)`, not `map f xs`

### Method call on nil

Use `some->`:

```phel
(some-> user (get :profile) (.-email))
```

### PHP assoc array vs Phel map

```phel
(php/array_merge #php {:a 1} #php {:b 2})        ; OK
(php/array_merge (phel->php {:a 1}) ...)       ; OK
(php-array-to-map arr)                         ; reverse
```

### `:phel/static-type` mismatch

Compile-time arg vs. param tag, `recur` vs. binding tag, or tail vs. declared return tag. Either fix the call site, widen the tag (`^"?int"`, `^"int|string"`), or drop the tag if the caller really is dynamic.

### Profiling a slow path

```bash
./vendor/bin/phel profile src/main.phel --format=both --output=profile.json
```

Per-fn self/total/avg/max + compile-phase costs. Tag the top-N self-time fns; see `tasks/typed-defn.md`.

### Macro surprises

```phel
(macroexpand-1 '(my-macro a b))
(macroexpand   '(-> x f g))
```

Check `~`, `` ` ``, auto-gensym `name#`.

## Logs (gitignored)

| File | Contents |
|------|----------|
| `phel-error.log` | compiler + runtime errors |
| `phel-debug.log` | `phel run --debug` traces |

## Tools

- `(doc sym)`, `(resolve 'sym)`, `(macroexpand-1 ...)`, `(macroexpand ...)`
- `(require 'phel.pprint :refer [pprint])`, `(pprint x)`
- `./vendor/bin/phel run --debug <file>`
- `./vendor/bin/phel cache:clear` (stale cache after core/macro edits)
- `./vendor/bin/phel doc <fn> --format=json` (no REPL needed)
- `./vendor/bin/phel lint <file> --format=json`, `./vendor/bin/phel explain <PHELnnn>`

## Gotchas

- Error points to FORM start, not sub-expression. Read outward.
- PHP fatal errors bypass Phel `catch`.
- `"a\\b"` is `a\b`.

## See also

- `tasks/repl-workflow.md`, `tasks/typed-defn.md`, `tasks/write-macros.md`
- <https://phel-lang.org/documentation/language/error-handling/>, <https://phel-lang.org/documentation/language/php-interop/>

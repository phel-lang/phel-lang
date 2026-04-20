# Debug errors

Errors print `file:line:col` when available. Re-read the snippet there.

## Categories

| Phase | Typical shape |
|-------|---------------|
| Lexer | Unbalanced paren, unterminated string |
| Reader | Invalid literal, bad `#reader` form |
| Analyzer | "Can not resolve symbol", arity mismatch, bad special form |
| Emitter | Rare; compiler bug, report upstream |
| Runtime | PHP exception, nil method call |

## Common fixes

### "Can not resolve symbol X"

Missing `:require`/`:use`, typo, shadowed core fn, wrong `:as`/`:refer`.

### "Cannot find namespace"

File path must match ns:

| Layout | ns | File |
|--------|----|----|
| flat | `my-app\main` | `src/main.phel` |
| flat | `my-app\users\repo` | `src/users/repo.phel` |
| nested | `my-app\main` | `src/phel/main.phel` |

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

### `php/->` on nil

Use `some->`:

```phel
(some-> user (get :profile) (.-email))
```

### PHP assoc array vs Phel map

```phel
(php/array_merge #php {"a" 1} #php {"b" 2})    ; OK
(php/array_merge (to-php-array {:a 1}) ...)    ; OK
(php-array-to-map arr)                         ; reverse
```

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

- `(doc sym)`, `(resolve 'sym)`, `(macroexpand-1 ...)`
- `./vendor/bin/phel run --debug <file>`
- `./vendor/bin/phel cache:clear`

## Gotchas

- Error points to FORM start, not sub-expression. Read outward.
- PHP fatal errors bypass Phel `catch`.
- `"a\\b"` is `a\b`.

## Next

`docs/patterns.md` § Error Handling, `docs/php-interop.md` § Error Handling, `tasks/repl-workflow.md`

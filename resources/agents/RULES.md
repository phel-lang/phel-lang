# Phel rules + CLI

Single source for every skill adapter.

## Rules

1. Verify fn names with `(doc <fn>)` or grep `src/phel/core/`. No invention.
2. Collections immutable. `(conj v x)` returns new; rebind with `def`/`let`, or use `atom`.
3. Top-level side effects break `phel build`. Guard with `(when-not *build-mode* ...)`.
4. PHP interop: `(php/fn args)`, `(php/-> obj (method args))`, `(php/:: Class (static args))`, `(php/new Class args)`. Shorthands: `(.method obj args)`, `(.-prop obj)`, `(Class/method args)`, `Class/CONST`.
5. Threading: `->` first-arg, `->>` last-arg, `some->` / `some->>` nil-safe, `cond->` conditional.
6. Only `false` and `nil` are falsy. `0`, `""`, `[]` truthy.
7. Namespaces need ≥ 2 segments (`app\main`). File path matches ns under src dir.
8. Comments: `;` inline, `;;` standalone, `#_` form, `#| |#` block.
9. PHP assoc array: `#php {"k" "v"}` or `(to-php-array m)`. Not `{:k "v"}`.
10. Catch PHP: `(catch php\SomeException e ...)`.

## New features (v0.30 – main)

Use these when appropriate — they are stable and tested.

| Feature | Syntax | Since |
|---------|--------|-------|
| Records | `(defrecord Point [x y])` → `(->Point 1 2)`, `(map->Point {:x 1 :y 2})` | v0.32 |
| Protocols | `(defprotocol Drawable (draw [this]))` + `(extend-type :string Drawable (draw [s] ...))` | v0.31 |
| Multimethods | `(defmulti area :shape)` + `(defmethod area :circle [{:radius r}] ...)` | v0.30 |
| Transducers | `(into [] (filter odd?) [1 2 3])`, `(transduce (map inc) + 0 coll)` | v0.31 |
| Regex literals | `#"^\d+$"`, `(re-find #"\d+" "abc123")` → `"123"` | v0.31 |
| Pretty-print | `(require phel\pprint)` → `(pprint data)` | v0.30 |
| Sorted colls | `(sorted-map :a 1 :b 2)`, `(sorted-set 3 1 2)` | v0.32 |
| `condp` | `(condp = x 1 "one" 2 "two" "other")` | v0.32 |
| `defrecord` w/ protocols | `(defrecord Foo [x] MyProto (my-fn [this] ...))` | v0.32 |
| `doseq` | `(doseq [x :in coll] (println x))` — side-effecting iteration | v0.31 |
| `for` comprehension | `(for [x :in xs :when (odd? x)] (* x x))` — builds sequence | v0.31 |

## Gotchas

See [`tasks/common-gotchas.md`](tasks/common-gotchas.md) for details. Quick summary:

- **CLI args**: use `*argv*` (vec of strings after script path), not `php/$argv`.
- **`transduce` + `max`/`min`**: these don't support 0-arity; pass explicit init: `(transduce xf (fn [a b] (max a b)) 0 coll)`.
- **`for` vs `doseq`**: `for` builds a sequence (lazy); `doseq` is for side effects. Don't use `for` for `println` loops.
- **`phel\string`**: was `phel\str` before v0.33.

## CLI

| Task | Command |
|------|---------|
| Scaffold | `./vendor/bin/phel init [name] [--nested\|--minimal]` |
| Run | `./vendor/bin/phel run <file>` |
| Eval | `./vendor/bin/phel eval '<expr>'` |
| REPL | `./vendor/bin/phel repl` |
| Test | `./vendor/bin/phel test [path]` |
| Build | `./vendor/bin/phel build` |
| Doc | `./vendor/bin/phel doc <fn>` |
| Format | `./vendor/bin/phel format <file>` |
| Install skill | `./vendor/bin/phel agent-install <platform>\|--all` |

## Workflow

1. `phel init` if empty.
2. Unknowns → REPL `(doc <fn>)` before guessing.
3. Code `src/<ns>.phel` (flat) or `src/phel/<ns>.phel` (`--nested`).
4. `phel\test`: `deftest`, `is`. Run `phel test`.
5. `phel run` or web entry.

## Commits

Conventional (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`). No AI/LLM references.

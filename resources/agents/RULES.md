# Phel rules + CLI

Single source for every skill adapter.

## Rules

1. Verify fn names with `phel doc <fn> --format=json`, `(doc <fn>)`, or grep `vendor/phel-lang/phel-lang/src/phel/core/`. No invention.
2. Collections immutable. `(conj v x)` returns new; rebind with `def`/`let`, or use `atom`.
3. Top-level side effects break `phel build`. Guard with `(when-not *build-mode* ...)`.
4. PHP interop: `(new Class args)`, `(.method obj args)`, `(.-prop obj)`, `(Class/method args)`, `Class/CONST`. A static property reads as `Class/$prop` and assigns as `(set! Class/prop v)`; the sigil belongs to the read only. Host functions keep the prefix: `(php/strlen s)`. `php/new`, `php/->` and `php/::` are rejected as source.
5. Threading: `->` first-arg, `->>` last-arg, `some->` / `some->>` nil-safe, `cond->` conditional.
6. Only `false` and `nil` are falsy. `0`, `""`, `[]` truthy.
7. Namespaces need ≥ 2 segments. Prefer `.` separator (`app.main`); `\` still parses but is deprecated. File path matches ns under src dir.
8. Comments: `;` inline, `;;` standalone, `#_` skips the next form.
9. PHP assoc array: `#php {:k "v"}` stringifies keyword keys; use `(phel->php m)` for an existing Phel map (`to-array` gives an indexed array of `[k v]` pairs).
10. Catch PHP: `(catch SomeException e ...)`.
11. Annotate hot-path `defn` with `:tag` on params + return for PHP type emission, JIT-friendly call shape, and compile-time mismatch diagnostics. See `tasks/typed-defn.md`.

## New features (v0.30 – main)

Use these when appropriate; stable and tested.

| Feature | Syntax | Since |
|---------|--------|-------|
| Records | `(defrecord Point [x y])` → `(->Point 1 2)`, `(map->Point {:x 1 :y 2})` | v0.32 |
| Protocols | `(defprotocol Drawable (draw [this]))` + `(extend-type :string Drawable (draw [s] ...))` | v0.31 |
| Multimethods | `(defmulti area :shape)` + `(defmethod area :circle [{:radius r}] ...)` | v0.30 |
| Transducers | `(into [] (filter odd?) [1 2 3])`, `(transduce (map inc) + 0 coll)` | v0.31 |
| Regex literals | `#"^\d+$"`, `(re-find #"\d+" "abc123")` → `"123"` | v0.31 |
| Pretty-print | `(require phel.pprint)` → `(pprint data)` | v0.30 |
| Sorted colls | `(sorted-map :a 1 :b 2)`, `(sorted-set 3 1 2)` | v0.32 |
| `condp` | `(condp = x 1 "one" 2 "two" "other")` | v0.32 |
| `defrecord` w/ protocols | `(defrecord Foo [x] MyProto (my-fn [this] ...))` | v0.32 |
| `doseq` | `(doseq [x :in coll] (println x))`; side-effecting iteration | v0.31 |
| `for` comprehension | `(for [x :in xs :when (odd? x)] (* x x))`; builds sequence | v0.31 |
| `:tag` types | `(defn ^int square [^int x] (* x x))`, `^"?int"`, `^Foo.Bar`, `^{:tag "..."}`. Emits PHP type decls; static checker rejects literal mismatches | main |
| Return inference | Tagged params + tail primitive op (`(php/+ ...)` -> `int`, `(php/. ...)` -> `string`, comparisons -> `bool`) infers return; `if` / `let` / `loop` propagate. Explicit `:tag` always wins | main |
| `^:async` defn | `(defn ^:async fetch [url] (await (http-get url)))`; body wrapped in `async`, returns `Amp\Future` | main |
| `^:memoize` / `^{:memoize-lru N}` | `(defn ^{:memoize-lru 32} fib [^int n] ...)`; opt-in caching by arg vector, LRU bound optional | main |
| `^:redef` defn | `(defn ^:redef fetch [url] ...)`; opts out of inlining at `optimizationLevel` 2 so `with-redefs` and `phel.mock` still intercept the call | main |

`:tag` reader shorthands:

```phel
(defn ^int  add  [^int a ^int b] (+ a b))               ; param + return
(defn ^"?int" maybe-id [^string s] ...)                 ; nullable
(defn ^DateTimeImmutable now [] (new DateTimeImmutable))
(defn ^{:tag "array"} pairs [m] ...)                    ; map form
```

Defn-name tag propagates to every arity unless an arity vector overrides it. See `tasks/typed-defn.md`.

## Gotchas

See [`tasks/common-gotchas.md`](tasks/common-gotchas.md) for details. Quick summary:

- **CLI args**: use `*argv*` (vec of strings after script path), not `php/$argv`.
- **`transduce` + `max`/`min`**: these don't support 0-arity; pass explicit init: `(transduce xf max 0 coll)`. A hand-written 2-arity reducer needs `completing`.
- **`for` vs `doseq`**: `for` builds a sequence (lazy); `doseq` is for side effects. Don't use `for` for `println` loops.
- **`phel.string`**: was `phel.str` before v0.33.
- **Clojure habits**: no JVM classes (`Math/abs` is `abs`, `Integer/parseInt` is `parse-long`), no `:refer :all`, no `:import`, no top-level `require` in a file. Table in `tasks/common-gotchas.md`.

## Check loop

After every edit to a `.phel` file, before running it:

1. `./vendor/bin/phel lint <file> --format=json`. Fix every `error`; read every `warning`.
2. `./vendor/bin/phel explain <code>` for a `PHELnnn` code you do not recognise, from lint or any error.
3. `./vendor/bin/phel doc <fn> --format=json` before calling a fn you have not verified.
4. `./vendor/bin/phel test` once lint is clean.

## CLI

| Task | Command |
|------|---------|
| Scaffold | `./vendor/bin/phel init [name] [--nested\|--minimal]` |
| Run | `./vendor/bin/phel run <file>` |
| Eval | `./vendor/bin/phel eval '<expr>'` |
| REPL | `./vendor/bin/phel repl` |
| Test | `./vendor/bin/phel test [path]` |
| Build | `./vendor/bin/phel build` |
| Doc | `./vendor/bin/phel doc <fn> [--format=json]` |
| Lint | `./vendor/bin/phel lint [paths] [--format=json]` (exit 1 on errors) |
| Analyze | `./vendor/bin/phel analyze <file>` (analyzer diagnostics as JSON) |
| Explain error | `./vendor/bin/phel explain <PHELnnn>` (no code lists every one) |
| Show emitted PHP | `./vendor/bin/phel compile '<expr>'` |
| Format | `./vendor/bin/phel format <file>` (`--dry-run` to check; `--exclude='<glob>'` or `format-exclude` config skips generated files) |
| Fix parens | `./vendor/bin/phel balance <file> --fix` |
| Profile | `./vendor/bin/phel profile <path> [--format=text\|json\|both] [--output=<file>]` |
| Install skill | `./vendor/bin/phel agent-install <platform>\|--all` |

## Workflow

1. `phel init` if empty.
2. Unknowns → `phel doc <fn> --format=json` or REPL `(doc <fn>)` before guessing.
3. Code `src/<ns>.phel` (flat) or `src/phel/<ns>.phel` (`--nested`).
4. Run the check loop above on every file you touch.
5. `phel.test`: `deftest`, `is`. Run `phel test`.
6. `phel run` or web entry.
7. Hot loops: add `:tag` to params + return; `phel profile` to find them.

After writing or editing a `.phel` file, `phel balance <file> --fix` appends any
closing delimiter you dropped. It only appends: a surplus or mismatched closer
and an unterminated string are reported and left for you to fix, since each has
more than one plausible repair.

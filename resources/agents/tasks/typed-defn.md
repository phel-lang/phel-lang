# Typed `defn` (`:tag` metadata)

`:tag` on a `defn` (or `fn`) emits a PHP type declaration on the compiled signature: per-param, per-arity, plus the return slot. Three wins:

- **DX**: literal call-site mismatches and `recur` / tail-position mismatches surface as Phel diagnostics at compile time, not runtime PHP `TypeError`.
- **Perf**: typed signatures unlock OPcache JIT tracing on the generated PHP. Hot kernels (`fib`, `sum-squares`, `mandel-point`) bench faster typed than untyped.
- **Interop**: PHP callers see real type declarations.

## Reader shorthands

```phel
(defn ^int      square  [^int x]            (* x x))
(defn ^float    avg     [^"int|float" a ^"int|float" b] (/ (+ a b) 2))
(defn ^"?int"   parse   [^string s]          (try (php/intval s) (catch \Throwable _ nil)))
(defn ^string   greet   [^string name]       (str "Hello, " name "!"))
(defn ^bool     valid?  [^string s]          (php/!== "" s))
(defn ^"\\DateTimeImmutable" now [] (php/new \DateTimeImmutable))
(defn ^{:tag "array"} pairs [m] (to-php-array m))
```

| Shorthand | Compiles to |
|-----------|-------------|
| `^int`, `^string`, `^bool`, `^float`, `^array`, `^void`, `^mixed` | builtin scalars |
| `^"?int"` | `?int` (nullable) |
| `^"int\|string"` | union |
| `^"\\Foo\\Bar"` | leading `\\` for FQ class name |
| `^{:tag "..."}` | map form (any string accepted by PHP) |

## Defn-name tag propagation

A tag on the defn name applies to every arity's return slot unless the arity vector overrides it:

```phel
(defn ^int fib                  ; default return :int for both arities
  ([^int n] (fib n 0 1))
  ([^int n ^int a ^int b]
   (if (zero? n) a (recur (dec n) b (+ a b)))))

(defn ^int parse-or-default     ; default return :int...
  ([^string s] (parse-or-default s 0))
  ([^string s ^"?int" fallback]
   ^"?int"                      ; ...overridden to ?int for this arity
   (or (parse s) fallback)))
```

## Return inference

If you tag the params and the tail expression is a primitive op, the compiler infers the return type. Explicit `^tag` on the name still wins.

| Tail form | Inferred |
|-----------|----------|
| `(php/+ ...)`, `(php/* ...)`, `(php/- ...)` | `int` if all int args, else `float` |
| `(php/. ...)` (PHP concat) | `string` |
| `(php/=== ...)`, `(php/< ...)`, `(php/> ...)` | `bool` |
| `if`, `let`, `loop` | propagate from each branch |

```phel
(defn add [^int a ^int b]      ; no return tag; inferred as `int`
  (+ a b))

(defn label [^string s]         ; inferred `string`
  (php/. "row:" s))
```

## Static checker

Compile time: literal call args, `recur` args against the surrounding binding tags, and the tail literal against the declared return are all checked. Mismatch is a `:phel/static-type` diagnostic with `file:line:col`, no PHP `TypeError`.

```phel
(defn ^int square [^int x] (* x x))

(square "abc")                  ; compile error: arg 1 expects int, got string
```

## Combine with metadata flags

Type tags compose with `^:async`, `^:memoize`, `^{:memoize-lru N}`:

```phel
(defn ^{:memoize-lru 256 :tag "int"} fib
  [^int n]
  (if (< n 2) n (+ (fib (- n 1)) (fib (- n 2)))))

(defn ^:async ^"\\Amp\\Future" fetch [^string url]
  (await (http-get url)))
```

`^{:async false}` opts out without removing the metadata key; same for `:memoize false`.

## When to add tags

| Scenario | Tag? |
|----------|------|
| Hot inner loop, primitive args | yes; inference fills the return |
| Public API on a record / handler | yes; doubles as documentation + interop |
| Glue code with mixed shapes | usually no; tag adds noise |
| `defn-` private wrapper | only if the body benefits |

## Find hot paths

```bash
./vendor/bin/phel profile src/main.phel --format=both --output=profile.json
```

Per-fn call counts and self/total/avg/max timings, plus compile-phase cost (lex, parse, read, analyze, emit). Tag the top-N self-time fns first.

## Gotchas

- `^int` tags a single symbol; `^{:tag "..."}` is the only form for arbitrary type strings.
- `:tag` on `def` of a non-fn is ignored (no PHP slot to type).
- A bound symbol passed to a typed param is checked at runtime (PHP-level), not compile time; only literal mismatches are static.
- `(php/+ 1 1.0)` infers `float`, not `int`. Promotion follows PHP rules.
- Class FQNs need leading `\\`: `^"\\App\\User"`, otherwise the reader treats it as a relative ns symbol.

## See also

- `RULES.md` § New features
- `tasks/use-core-lib.md` for non-typed core ops
- `docs/schema-guide.md` § Function instrumentation for runtime contracts on top of `:tag`

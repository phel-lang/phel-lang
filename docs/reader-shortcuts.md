# Reader Shortcuts and Special Syntax

Reader shortcuts are resolved by the reader during compilation.

## Collection Literals

```phel
[1 2 3]       ; (vector 1 2 3)      [] empty vector
{:a 1 :b 2}   ; (hash-map :a 1 :b 2) {} empty map
#{1 2 3}      ; (hash-set 1 2 3)    #{} empty set
'(1 2 3)      ; (list 1 2 3)        '() empty list
```

## Quote and Quasiquote

```phel
'x            ; the symbol x, not evaluated  (= (quote x))
'(+ 1 2)      ; the list (+ 1 2), not 3
'[1 2 3]      ; the vector [1 2 3]
```

Quasiquote (`` ` ``) is like quote but allows selective evaluation with unquote (`~`) and unquote-splicing (`~@`):

```phel
`(1 2 3)                          ; => (1 2 3)
(let [x 5] `(1 ~x 3))             ; => (1 5 3)     ; ~ evaluates one form
(let [xs [2 3 4]] `(1 ~@xs 5))    ; => (1 2 3 4 5) ; ~@ splices a sequence
```

> **Deprecated:** `,` (unquote) and `,@` (unquote-splicing) reader macros. Use `~` and `~@`.

### Auto-gensym `name#`

Inside a syntax-quote, a symbol ending in `#` expands to a fresh unique name. All occurrences of the same `name#` in one syntax-quote share that name, giving hygienic macros without calling `gensym`:

```phel
(defmacro time [expr]
  `(let [start# (php/microtime true)
         ret#   ~expr]
     (println "Elapsed:" (- (php/microtime true) start#) "secs")
     ret#))
```

Each expansion produces a fresh `start__123__auto__` / `ret__124__auto__` pair, so generated bindings can't collide with user code.

> **Deprecated:** `name$` as an auto-gensym suffix. Use `name#`.

## Reader Conditionals

Resolved at parse time. `#?()` selects one form by platform key; `#?@()` splices a collection. Phel selects `:phel`, falls back to `:default`.

```phel
#?(:phel (php/time) :clj (System/currentTimeMillis))  ; => (php/time) in Phel
#?(:clj 99 :default 0)                                 ; => 0
[1 #?@(:phel [2 3]) 4]                                 ; => [1 2 3 4]
```

`#?@()` is only valid inside a collection. See [Reader Conditionals](reader-conditionals.md) for full documentation.

## Deref `@`

```phel
@my-atom              ; same as (deref my-atom)
```

## Tagged Literals `#<tag> form`

Tagged literals convert a form to a value at read time. Three ship built-in:

```phel
#inst "2026-01-01T00:00:00Z"                   ; reads as \DateTimeImmutable
#regex "\\d+"                                   ; reads as a PCRE pattern string
#uuid "550e8400-e29b-41d4-a716-446655440000"   ; reads as Phel\Lang\UUID
```

Register your own with `phel.reader/register-tag`:

```phel
(ns my-app.main
  (:require phel.reader :refer [register-tag]))

(register-tag "money" (fn [s] {:kind :money :raw s}))

#money "10.00 EUR"
;; => {:kind :money :raw "10.00 EUR"}
```

For project-wide tags, drop a `data-readers.phel` at any source root. It is auto-loaded and should register each tag explicitly:

```phel
;; src/phel/data-readers.phel
(ns my-app.data-readers
  (:require phel.reader :refer [register-tag]))

(register-tag "point" (fn [[x y]] {:x x :y y}))
```

Related helpers in `phel.reader`: `tag-registered?`, `unregister-tag`, `registered-tags`.

## Regex Literals `#"..."`

```phel
#"\\d+"                       ; same as (re-pattern "\\d+")
(re-find #"\\d+" "abc123")    ; => "123"
```

## Anonymous Functions `#(...)`

`%` placeholders: `%` (or `%1`) is the first arg, `%2` the second, `%&` the rest:

```phel
#(* % %)             ; (fn [x] (* x x))
#(+ %1 %2)           ; (fn [a b] (+ a b))
#(apply str %&)      ; %& captures rest args

(map #(* % 2) [1 2 3])         ; => [2 4 6]
(filter #(> % 5) [3 6 2 8 4])  ; => [6 8]
(reduce #(+ %1 %2) 0 [1 2 3 4]) ; => 10
```

### Short Function Syntax `|(...)` (Deprecated)

> **Deprecated:** Use `#(...)` with `%` placeholders. `|(...)` will be removed in a future release.

Positional parameters `$1`, `$2`, ... (1-indexed), `$&` for rest args; `$` is shorthand for `$1`:

```phel
|(* $ $)        ; (fn [x] (* x x))
|(+ $1 $2)      ; (fn [a b] (+ a b))
|(apply f $&)   ; $& captures all arguments
```

## Comments

```phel
;; standalone comment
(+ 1 2)           ; inline comment
(println 1 #_ 2 3)    ; #_ skips the next form  => prints: 1 3
[1 #_(+ 2 3) 4]       ; => [1 4]
```

> **Deprecated:** `#` as a line comment character (use `;`); `#| ... |#` multiline blocks (use `(comment ...)`).

## Metadata `^`

Attaches metadata to the following form:

```phel
^{:doc "Example"} (defn foo [] ...)
^:private (def x 10)
```

## Summary Table

| Syntax      | Name               | Description                                    | Example            |
|-------------|--------------------|------------------------------------------------|--------------------|
| `[]`        | Vector             | Ordered indexed collection                     | `[1 2 3]`          |
| `{}`        | Hash Map           | Key-value pairs                                | `{:a 1 :b 2}`      |
| `#{}`       | Set                | Unique unordered values                        | `#{1 2 3}`         |
| `'()`       | List               | Quoted list (prevents eval)                    | `'(1 2 3)`         |
| `'`         | Quote              | Prevent evaluation                             | `'x`               |
| `` ` ``     | Quasiquote         | Quote with selective eval                      | `` `(1 ~x)``       |
| `~`         | Unquote            | Evaluate within quasiquote                     | `~x`               |
| `~@`        | Unquote-splice     | Splice sequence in quasiquote                  | `~@xs`             |
| `name#`     | Auto-gensym        | Hygienic generated symbol in syntax-quote      | `` `(let [g# ~x] g#)`` |
| `#?()`      | Reader conditional | Platform-specific code                         | `#?(:phel 1)`      |
| `#?@()`     | Conditional splice | Splice by platform                             | `#?@(:phel [1 2])` |
| `#<tag>`    | Tagged literal     | Call the tag's reader function                 | `#inst "2026-01-01T00:00:00Z"` |
| `@`         | Deref              | Dereference an atom                            | `@my-atom`         |
| `#'`        | Var-quote          | Get the `PhelVar` handle for a def             | `#'my-fn`          |
| `#"..."`    | Regex literal      | PCRE pattern                                   | `#"\\d+"`          |
| `#(...)`    | Lambda             | Anonymous function (`%` args)                  | `#(+ %1 %2)`       |
| `\|()`      | Lambda (old)       | Anonymous function (`$` args) **(deprecated)** | `\|(+ $1 $2)`      |
| `;` or `;;` | Line comment       | Comment to end of line                         | `;; comment`       |
| `#\| \|#`   | Multiline comment  | Block comment   **(deprecated)**               | `#\| ... \|#`      |
| `#_`        | Inline comment     | Comment out next form                          | `#_ expr`          |
| `^`         | Metadata           | Attach metadata                                | `^:private`        |

## See Also

- [Reader Conditionals](reader-conditionals.md): Cross-platform code with `#?()` and `#?@()`
- Core library functions: `vector`, `hash-map`, `hash-set`, `list`
- Coercion functions: `vec`, `set`
- Quote functions: `quote`, `quasiquote`, `unquote`
- Function definition: `fn`, `defn`

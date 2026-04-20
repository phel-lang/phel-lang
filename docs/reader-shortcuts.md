# Reader Shortcuts and Special Syntax

Phel provides several reader shortcuts and special syntax forms for concise code. These shortcuts are processed by the reader during compilation.

## Collection Literals

### Vectors `[]`
```phel
[1 2 3]           ; Shortcut for (vector 1 2 3)
[]                ; Empty vector
```

### Hash Maps `{}`
```phel
{:a 1 :b 2}       ; Shortcut for (hash-map :a 1 :b 2)
{}                ; Empty hash map
```

### Sets `#{}`
```phel
#{1 2 3}          ; Shortcut for (hash-set 1 2 3)
#{}               ; Empty set
```

### Lists `'()`
```phel
'(1 2 3)          ; Shortcut for (list 1 2 3)
'()               ; Empty list (quote prevents evaluation)
```

## Quote and Quasiquote

### Quote `'`
Prevents evaluation of the following form:
```phel
'x                ; The symbol x (not evaluated)
'(+ 1 2)          ; The list (+ 1 2), not the result 3
'[1 2 3]          ; The vector [1 2 3]
```

Equivalent to: `(quote x)`

### Quasiquote `` ` ``
Like quote, but allows selective evaluation with unquote:
```phel
`(1 2 3)          ; => (1 2 3)
`(1 ~(+ 1 1) 3)   ; => (1 2 3)
```

Equivalent to: `(quasiquote ...)`

### Unquote `~`
Evaluates an expression within a quasiquote:
```phel
(let [x 5]
  `(1 ~x 3))      ; => (1 5 3)
```

> **Deprecated:** `,` as an unquote reader macro is deprecated. Use `~` instead to match Clojure's syntax.

### Unquote-splicing `~@`
Evaluates and splices a sequence into the containing form:
```phel
(let [xs [2 3 4]]
  `(1 ~@xs 5))    ; => (1 2 3 4 5)
```

> **Deprecated:** `,@` as an unquote-splicing reader macro is deprecated. Use `~@` instead to match Clojure's syntax.

### Auto-gensym `name#`
Inside a syntax-quote, a symbol ending in `#` is replaced with a freshly generated, unique symbol. All occurrences of the same `name#` within the same syntax-quote resolve to the same generated name, which makes hygienic macros easy to write without manually calling `gensym`:

```phel
(defmacro time
  [expr]
  `(let [start# (php/microtime true)
         ret#   ~expr]
     (println "Elapsed:" (- (php/microtime true) start#) "secs")
     ret#))
```

Each macro expansion produces a fresh `start__123__auto__` / `ret__124__auto__` pair, so the generated bindings cannot collide with user code.

> **Deprecated:** `name$` as an auto-gensym suffix is deprecated. Use `name#` instead to match Clojure's reader macro.

## Reader Conditionals

### Platform Conditional `#?()`
Selects a form based on the platform (resolved at parse time):
```phel
#?(:phel (php/time) :clj (System/currentTimeMillis))
; => (php/time) when compiled by Phel

#?(:clj 99 :default 0)
; => 0 (fallback when :phel is absent)
```

### Platform Conditional Splicing `#?@()`
Splices elements from a collection into the surrounding form:
```phel
[1 #?@(:phel [2 3]) 4]
; => [1 2 3 4]
```

Only valid inside collections (lists, vectors, maps, sets). See [Reader Conditionals](reader-conditionals.md) for full documentation.

## Deref `@`

Shorthand for `(deref ...)`:
```phel
@my-atom              ; Same as (deref my-atom)
```

## Tagged Literals `#<tag> form`

Tagged literals let the reader convert an arbitrary form into a different value. Phel ships two built-ins:

```phel
#inst "2026-01-01T00:00:00Z"     ; reads as \DateTimeImmutable
#regex "\\d+"                     ; reads as a PCRE pattern string
```

Register your own with `phel\reader/register-tag`:

```phel
(ns my-app\main
  (:require phel\reader :refer [register-tag]))

(register-tag "money" (fn [s] {:kind :money :raw s}))

;; Now the reader accepts:
#money "10.00 EUR"
;; => {:kind :money :raw "10.00 EUR"}
```

For project-wide tags, drop a `data-readers.phel` at any source root — it is auto-loaded and should register each tag explicitly:

```phel
;; src/phel/data-readers.phel
(ns my-app\data-readers
  (:require phel\reader :refer [register-tag]))

(register-tag "point" (fn [[x y]] {:x x :y y}))
```

Related helpers in `phel\reader`: `tag-registered?`, `unregister-tag`, `registered-tags`.

## Regex Literals `#"..."`

Creates a PCRE pattern string:
```phel
#"\\d+"               ; Same as (re-pattern "\\d+")
(re-find #"\\d+" "abc123")  ; => "123"
```

## Anonymous Functions

### Clojure-Style `#(...)`
Creates anonymous functions with `%` parameter placeholders:
```phel
#(+ %1 %2)           ; Function taking 2 args
#(* % %)             ; % is shorthand for %1
#(apply str %&)       ; %& captures rest args
```

### Short Function Syntax `|(...)` (Deprecated)
> **Deprecated:** Use `#(...)` with `%` placeholders instead. `|(...)` will be removed in a future release.

Creates anonymous functions with positional parameters:
```phel
|(+ $1 $2)        ; Function taking 2 args
|(* $1 $1)        ; Function squaring its argument
|(apply f $&)     ; $& captures all arguments
```

**Parameters:**
- `$1`, `$2`, `$3`, ... - Positional arguments (1-indexed)
- `$&` - All arguments as a sequence (rest args)

**Examples:**
```phel
(map |(* $ 2) [1 2 3])
;; => [2 4 6]

(filter |(> $ 5) [3 6 2 8 4])
;; => [6 8]

(reduce |(+ $1 $2) 0 [1 2 3 4])
;; => 10
```

Equivalent to traditional `fn` syntax:
```phel
|(* $ $)        ; Same as (fn [x] (* x x))
|(+ $1 $2)      ; Same as (fn [a b] (+ a b))
```

## Comments

### Line Comments `;`
Comment from the character to the end of the line. By convention, use `;;` for
standalone line comments and `;` for inline comments after code:
```phel
;; This is a standalone comment
(+ 1 2)           ; inline comment
```

> **Deprecated:** `#` as a line comment character is deprecated. Use `;` instead.

### Multiline Comments `#| |#` (Deprecated)
> **Deprecated:** Use `(comment ...)` instead. `#| |#` will be removed in a future release.

Comment blocks spanning multiple lines:
```phel
#|
  This is a multiline comment.
  Everything between #| and |# is ignored.
|#
```

### Inline Comment `#_`
Comments out the next form entirely:
```phel
(println 1 #_ 2 3)    ; => prints: 1 3
[1 #_(+ 2 3) 4]       ; => [1 4]
```

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
| `#"..."`    | Regex literal      | PCRE pattern                                   | `#"\\d+"`          |
| `#(...)`    | Lambda             | Anonymous function (`%` args)                  | `#(+ %1 %2)`       |
| `\|()`      | Lambda (old)       | Anonymous function (`$` args) **(deprecated)** | `\|(+ $1 $2)`      |
| `;` or `;;` | Line comment       | Comment to end of line                         | `;; comment`       |
| `#\| \|#`   | Multiline comment  | Block comment   **(deprecated)**               | `#\| ... \|#`      |
| `#_`        | Inline comment     | Comment out next form                          | `#_ expr`          |
| `^`         | Metadata           | Attach metadata                                | `^:private`        |

## See Also

- [Reader Conditionals](reader-conditionals.md) - Cross-platform code with `#?()` and `#?@()`
- Core library functions: `vector`, `hash-map`, `hash-set`, `list`
- Coercion functions: `vec`, `set`
- Quote functions: `quote`, `quasiquote`, `unquote`
- Function definition: `fn`, `defn`

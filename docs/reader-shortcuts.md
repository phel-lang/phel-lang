# Reader Shortcuts and Special Syntax

Phel provides several reader shortcuts and special syntax forms for concise code. These shortcuts are processed by the reader during compilation.

## Collection Literals

### Vectors `[]`
```phel
[1 2 3]           # Shortcut for (vector 1 2 3)
[]                # Empty vector
```

### Hash Maps `{}`
```phel
{:a 1 :b 2}       # Shortcut for (hash-map :a 1 :b 2)
{}                # Empty hash map
```

### Sets `#{}`
```phel
#{1 2 3}          # Shortcut for (set 1 2 3)
#{}               # Empty set
```

### Lists `'()`
```phel
'(1 2 3)          # Shortcut for (list 1 2 3)
'()               # Empty list (quote prevents evaluation)
```

## Quote and Quasiquote

### Quote `'`
Prevents evaluation of the following form:
```phel
'x                # The symbol x (not evaluated)
'(+ 1 2)          # The list (+ 1 2), not the result 3
'[1 2 3]          # The vector [1 2 3]
```

Equivalent to: `(quote x)`

### Quasiquote `` ` ``
Like quote, but allows selective evaluation with unquote:
```phel
`(1 2 3)          # => (1 2 3)
`(1 ~(+ 1 1) 3)   # => (1 2 3)
```

Equivalent to: `(quasiquote ...)`

### Unquote `,`
Evaluates an expression within a quasiquote:
```phel
(let [x 5]
  `(1 ~x 3))      # => (1 5 3)
```

### Unquote-splicing `,@`
Evaluates and splices a sequence into the containing form:
```phel
(let [xs [2 3 4]]
  `(1 ,@xs 5))    # => (1 2 3 4 5)
```

## Anonymous Functions

### Short Function Syntax `|(...)`
Creates anonymous functions with positional parameters:
```phel
|(+ $1 $2)        # Function taking 2 args
|(* $1 $1)        # Function squaring its argument
|(apply f $&)     # $& captures all arguments
```

**Parameters:**
- `$1`, `$2`, `$3`, ... - Positional arguments (1-indexed)
- `$&` - All arguments as a sequence (rest args)

**Examples:**
```phel
(map |(* $ 2) [1 2 3])
# => [2 4 6]

(filter |(> $ 5) [3 6 2 8 4])
# => [6 8]

(reduce |(+ $1 $2) 0 [1 2 3 4])
# => 10
```

Equivalent to traditional `fn` syntax:
```phel
|(* $ $)        # Same as (fn [x] (* x x))
|(+ $1 $2)      # Same as (fn [a b] (+ a b))
```

## Comments

### Line Comments `#` or `;`
Comment from the character to the end of the line:
```phel
# This is a comment
(+ 1 2)           # Add numbers
; This is also a comment
```

### Multiline Comments `#| |#`
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
(println 1 #_ 2 3)    # => prints: 1 3
[1 #_(+ 2 3) 4]       # => [1 4]
```

## Metadata `^`
Attaches metadata to the following form:
```phel
^{:doc "Example"} (defn foo [] ...)
^:private (def x 10)
```

## Summary Table

| Syntax     | Name              | Description                   | Example       |
|------------|-------------------|-------------------------------|---------------|
| `[]`       | Vector            | Ordered indexed collection    | `[1 2 3]`     |
| `{}`       | Hash Map          | Key-value pairs               | `{:a 1 :b 2}` |
| `#{}`      | Set               | Unique unordered values       | `#{1 2 3}`    |
| `'()`      | List              | Quoted list (prevents eval)   | `'(1 2 3)`    |
| `'`        | Quote             | Prevent evaluation            | `'x`          |
| `` ` ``    | Quasiquote        | Quote with selective eval     | `` `(1 ~x)``  |
| `,`        | Unquote           | Evaluate within quasiquote    | `,x`          |
| `,@`       | Unquote-splice    | Splice sequence in quasiquote | `,@xs`        |
| `\|()`     | Short function    | Anonymous function            | `\|(+ $1 $2)` |
| `#` or `;` | Line comment      | Comment to end of line        | `# comment`   |
| `#\| \|#`  | Multiline comment | Block comment                 | `#\| ... \|#` |
| `#_`       | Inline comment    | Comment out next form         | `#_ expr`     |
| `^`        | Metadata          | Attach metadata               | `^:private`   |

## See Also

- Core library functions: `vector`, `hash-map`, `set`, `list`
- Quote functions: `quote`, `quasiquote`, `unquote`
- Function definition: `fn`, `defn`

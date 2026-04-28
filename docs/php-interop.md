# PHP/Phel Interoperability Guide

This guide shows how to seamlessly work between PHP and Phel code.

## Quick Reference

| Task | PHP | Phel |
|------|-----|------|
| Call function | `strlen($str)` | `(php/strlen str)` |
| Method call | `$obj->method($arg)` | `(php/-> obj (method arg))` or `(.method obj arg)` |
| Static call | `DateTime::createFromFormat(...)` | `(php/:: DateTime (createFromFormat ...))` or `(DateTime/createFromFormat ...)` |
| New instance | `new DateTime()` | `(php/new DateTime)`, `(new DateTime)`, or `(DateTime.)` |
| Property access | `$obj->prop` | `(php/-> obj prop)` or `(.-prop obj)` |
| Array access | `$arr[$key]` | `(php/aget arr key)` |

## Calling PHP from Phel

### Functions

Prefix PHP functions with `php/`:

```phel
(ns app\example)

;; String functions
(php/strlen "hello")              ; => 5
(php/strtoupper "hello")          ; => "HELLO"
(php/str_replace "o" "0" "hello") ; => "hell0"

;; Array functions
(php/array_merge [1 2] [3 4])     ; => [1 2 3 4]
(php/count [1 2 3])               ; => 3

;; Math functions
(php/abs -5)                      ; => 5
(php/max 1 5 3)                   ; => 5

;; Namespaced functions — keep the original casing
(php/Amp\trapSignal [php/SIGINT php/SIGTERM])
(php/\Monolog\Logger\Utils\detectAndCleanUtf8 "input")
```

The `php/` prefix resolves any global or namespaced PHP function. For
namespaced functions the name after `php/` is the full PHP path (use `\\`
to disambiguate from the root namespace when needed). Case is preserved,
so `php/Amp\trapSignal` compiles to `\Amp\trapSignal(...)`.

To keep calls short, capture the function reference with `def`:

```phel
(def trap-signal php/\Amp\trapSignal)
(def detect-utf8 php/\Monolog\Logger\Utils\detectAndCleanUtf8)

(trap-signal [php/SIGINT php/SIGTERM])
(detect-utf8 "input")
```

### Classes and Objects

```phel
(ns app\example
  (:use DateTime DateInterval))

;; Create instance (all forms are equivalent)
(def now (php/new DateTime))
(def now (new DateTime))
(def now (DateTime.))
(def data (new stdClass))         ; PHP class names keep their PHP casing

;; Call methods
(php/-> now (format "Y-m-d"))     ; => "2024-01-15"
(php/-> now (getTimestamp))       ; => 1705334400

;; Method call shorthand — `.method` expands to `(php/-> ...)`
(.format now "Y-m-d")             ; => "2024-01-15"
(.getTimestamp now)               ; => 1705334400

;; Property access shorthand — `.-field` expands to `(php/-> ...)`
(.-y (new DateInterval "P1D"))    ; => 0

;; Chain method calls
(php/-> now
  (add (php/new DateInterval "P1D"))
  (format "Y-m-d H:i:s"))

;; Static methods
(php/:: DateTime (createFromFormat "Y-m-d" "2024-01-15"))
(DateTime/createFromFormat "Y-m-d" "2024-01-15")   ; shorthand

;; Static constants and properties — bare `Class/MEMBER` shorthand
(php/:: \DateTimeImmutable ATOM)                   ; => "Y-m-d\\TH:i:sP"
\DateTimeImmutable/ATOM                            ; shorthand

;; Access properties
(php/-> obj propertyName)
```

### Working with PHP Arrays

```phel
;; Create PHP array
(def php-arr (php/array 1 2 3))
(def assoc-arr (php-associative-array "name" "Alice" "age" 30))

;; `#php` reader literal (non-recursive): next vector/map becomes a PHP array
(def php-arr   #php [1 2 3])                    ; => (php-indexed-array 1 2 3)
(def assoc-arr #php {"name" "Alice" "age" 30})  ; => (php-associative-array "name" "Alice" "age" 30)

;; Access elements
(php/aget php-arr 0)              ; => 1
(php/aget assoc-arr "name")       ; => "Alice"

;; Set elements
(php/aset php-arr 0 99)           ; Modifies array
(php/aset assoc-arr "name" "Bob") ; Modifies array
```

### Namespaces and Imports

Use `:use` for PHP classes:

```phel
(ns app\services
  (:use PDO)                      ; Import single class
  (:use DateTime DateInterval)   ; Import multiple classes
  (:use Symfony\Component\HttpFoundation\Request))

;; Now use them without namespace prefix
(php/new PDO "mysql:host=localhost" "user" "pass")
(php/new Request)
```

### Requiring Phel Namespaces

`:require` accepts both list-style and Clojure-style vector entries, so the same form works in plain `.phel` files and shared `.cljc` files:

```phel
;; Phel-style list entry
(ns app\services
  (:require phel\string :as str)
  (:require phel\json :as json :refer [encode decode]))

;; Clojure-style vector entry — same meaning
(ns app\services
  (:require [phel\string :as str]
            [phel\json :as json :refer [encode decode]]))
```

You can also use `.` as an alternate namespace separator (in addition to `\`), which makes Clojure-shaped namespaces parse cleanly in `.cljc` files:

```phel
(ns my.cljc.file
  (:require [phel.string :as str]))
```

Both `phel\string` and `phel.string` resolve to the same namespace.

## Type Conversions

### Phel → PHP

```phel
;; Vectors → PHP arrays
(to-php-array [1 2 3])            ; => PHP [1, 2, 3]

;; Maps → PHP associative arrays
(to-php-array {:a 1 :b 2})        ; => PHP ["a" => 1, "b" => 2]

;; Keywords → strings
(name :keyword)                   ; => "keyword"
```

### PHP → Phel

```phel
;; PHP arrays → Phel collections
(php-array-to-map $php_assoc_array)  ; => {:key value}
(vals $php_indexed_array)            ; => [val1 val2 val3]

;; Indexed PHP array → vector
[1 2 3]                           ; Already works as Phel vector
```

## Calling Phel from PHP

### Basic Example

**hello.phel:**
```phel
(ns app\hello)

(defn greet [name]
  (str "Hello, " name "!"))

(defn add [a b]
  (+ a b))
```

**index.php:**
```php
<?php
require 'vendor/autoload.php';

use Phel\Run\RunFacade;

$phel = RunFacade::initialize();

// Call Phel function
$result = \Phel::callPhel('app\hello', 'greet', 'World');
echo $result; // => "Hello, World!"

$sum = \Phel::callPhel('app\hello', 'add', 5, 10);
echo $sum; // => 15
```

### Web Application Example

**routes.phel:**
```phel
(ns app\routes
  (:use Symfony\Component\HttpFoundation\Response))

(defn handle-home [request]
  (php/new Response "Welcome to Phel!" 200))

(defn handle-api [request]
  (let [data {:status "ok" :timestamp (php/time)}
        json (php/json_encode (to-php-array data))]
    (php/new Response json 200
      (php-associative-array "Content-Type" "application/json"))))
```

**index.php:**
```php
<?php
require 'vendor/autoload.php';

use Phel\Run\RunFacade;
use Symfony\Component\HttpFoundation\Request;

RunFacade::initialize();

$request = Request::createFromGlobals();
$path = $request->getPathInfo();

$response = match ($path) {
    '/' => \Phel::callPhel('app\routes', 'handle-home', $request),
    '/api' => \Phel::callPhel('app\routes', 'handle-api', $request),
    default => new Response('Not Found', 404),
};

$response->send();
```

## Error Handling

### Catching PHP Exceptions

```phel
(ns app\db
  (:use PDO PDOException))

(defn connect [dsn user pass]
  (try
    (php/new PDO dsn user pass)
    (catch PDOException e
      (println "Database error:" (php/-> e (getMessage)))
      nil)))

(defn safe-query [pdo sql]
  (try
    (php/-> pdo (query sql))
    (catch PDOException e
      {:error (php/-> e (getMessage))
       :code (php/-> e (getCode))})))
```

### Throwing Exceptions

```phel
(ns app\validator
  (:use InvalidArgumentException))

(defn validate-age [age]
  (when (< age 0)
    (throw (php/new InvalidArgumentException "Age cannot be negative")))
  age)
```

## Performance Tips

### Use Transients for Batch Updates

```phel
;; Slow - creates new collection each time
(reduce (fn [acc x] (conj acc (* x 2))) [] large-list)

;; Fast - mutable during build
(persistent
  (reduce (fn [acc x] (conj acc (* x 2))) (transient []) large-list))
```

### Prefer PHP Functions for Heavy Lifting

```phel
;; Good - use PHP's optimized functions
(php/array_map #(* % 2) php-array)

;; When you need Phel collections
(map #(* % 2) phel-vector)
```

### Avoid Unnecessary Conversions

```phel
;; Inefficient
(to-php-array (map inc (php-array-to-map php-data)))

;; Better - stay in PHP
(php/array_map inc php-data)
```

## Common Patterns

### Database Access

```phel
(ns app\db
  (:use PDO))

(defn query-all [pdo sql params]
  (let [stmt (php/-> pdo (prepare sql))]
    (php/-> stmt (execute (to-php-array params)))
    (php/-> stmt (fetchAll))))

(defn find-user [pdo id]
  (let [rows (query-all pdo "SELECT * FROM users WHERE id = ?" [id])]
    (when-not (php/empty rows)
      (php-array-to-map (php/aget rows 0)))))
```

### HTTP Requests

```phel
(ns app\http
  (:require phel\json :as json))

(defn fetch-json [url]
  (let [response (php/file_get_contents url)
        data (php/json_decode response true)]
    (php-array-to-map data)))

(defn post-json [url data]
  (let [json (json/encode data)
        opts (php-associative-array
               "http" (php-associative-array
                        "method" "POST"
                        "header" "Content-Type: application/json"
                        "content" json))
        context (php/stream_context_create opts)]
    (php/file_get_contents url false context)))
```

### File Operations

```phel
(ns app\files)

(defn read-lines [filename]
  (let [content (php/file_get_contents filename)]
    (php/explode "\n" content)))

(defn write-lines [filename lines]
  (let [content (php/implode "\n" (to-php-array lines))]
    (php/file_put_contents filename content)))
```

## Tips for PHP Developers

- **Immutability**: Phel collections don't mutate. `(conj vec item)` returns a *new* vector
- **No `$` sigil**: Variables don't need `$` in Phel
- **Keywords**: Use `:keyword` instead of strings for map keys
- **Truthiness**: Only `false` and `nil` are falsy (not `0` or `""`)
- **Parens matter**: `(func arg)` calls the function, `func` is just the value

## Tips for Clojure Developers

- **PHP interop**: Use `php/` prefix (not `.` or `..`)
- **Method calls**: `(php/-> obj (method))` not `(.method obj)`
- **Deref**: `@my-atom` works as shorthand for `(deref my-atom)`, just like Clojure
- **Import classes**: Use `:use` in `ns`, not `:import`
- **Require vectors**: Clojure-style `(:require [phel\string :as str :refer [upper-case]])` works alongside the older list form
- **Namespace separators**: Both `\` and `.` work — `phel\string` and `phel.string` resolve to the same namespace
- **Reader conditionals**: `#?(:phel ...)` and `#?@(:phel ...)` work for cross-platform `.cljc` files
- **Unquote**: Use `~` and `~@` inside syntax-quote (the older `,` / `,@` are deprecated)
- **Auto-gensym**: `name#` inside syntax-quote produces a unique symbol (the older `name$` is deprecated)
- **Macro env**: `&form` and `&env` are implicitly bound inside every `defmacro`, just like Clojure. `&env` is a map of in-scope locals keyed by symbol; `(:ns &env)` is always `nil` (matching Clojure on the JVM), so the standard `.cljc` `(if (:ns &env) "cljs" ...)` dialect-detection trick lands on the non-cljs branch
- **Lambda syntax**: `#(+ %1 %2)` is the recommended syntax. `|(+ $1 $2)` is deprecated
- **PHP arrays**: Work with them directly or convert to Phel collections

## See Also

- [Examples](examples/README.md) - Practical code samples
- [Reader Shortcuts](reader-shortcuts.md) - Syntax reference
- [Common Patterns](patterns.md) - Idiomatic Phel code

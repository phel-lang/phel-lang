# PHP/Phel Interop

Call PHP from Phel and Phel from PHP.

## Quick Reference

| Task | PHP | Phel |
|------|-----|------|
| Call function | `strlen($str)` | `(php/strlen str)` |
| Method call | `$obj->method($arg)` | `(php/-> obj (method arg))` or `(.method obj arg)` |
| Static call | `DateTime::createFromFormat(...)` | `(php/:: DateTime (createFromFormat ...))` or `(DateTime/createFromFormat ...)` |
| New instance | `new DateTime()` | `(php/new DateTime)`, `(new DateTime)`, or `(DateTime.)` |
| Property access | `$obj->prop` | `(php/-> obj prop)` or `(.-prop obj)` |
| Array access | `$arr[$key]` | `(php/aget arr key)` |
| By-reference arg | `$obj->m($out)` (writes back into `$out`) | `(php/-> obj (m (php/ref out)))` |

## Calling PHP from Phel

### Functions

Prefix any global or namespaced PHP function with `php/`:

```phel
(php/strlen "hello")              ; => 5
(php/str_replace "o" "0" "hello") ; => "hell0"
(php/array_merge [1 2] [3 4])     ; => [1 2 3 4]
(php/max 1 5 3)                   ; => 5
```

The name after `php/` is the full PHP path with case preserved. `\`, `.`, and `/` are interchangeable as namespace separators, so the backslash-free forms work in `.cljc` files or shared snippets without escaping:

```phel
(php/Amp\trapSignal [php/SIGINT php/SIGTERM])   ; all three compile to
(php/Amp.trapSignal [php/SIGINT php/SIGTERM])   ; \Amp\trapSignal(...)
(php/Amp/trapSignal [php/SIGINT php/SIGTERM])
```

Capture references with `def` to shorten calls:

```phel
(def trap-signal php/\Amp\trapSignal)
(trap-signal [php/SIGINT php/SIGTERM])
```

### Classes and Objects

```phel
(ns app.example
  (:use DateTime DateInterval))

;; Create instance (all equivalent); PHP class names keep their PHP casing
(def now (php/new DateTime))
(def now (new DateTime))
(def now (DateTime.))

;; Method call: php/-> or the .method shorthand
(php/-> now (format "Y-m-d"))     ; => "2024-01-15"
(.format now "Y-m-d")             ; => "2024-01-15"

;; Property access: php/-> or the .-field shorthand
(php/-> obj propertyName)
(.-y (new DateInterval "P1D"))    ; => 0

;; Chain method calls
(php/-> now
  (add (php/new DateInterval "P1D"))
  (format "Y-m-d H:i:s"))

;; Static methods: php/:: or the Class/member shorthand
(php/:: DateTime (createFromFormat "Y-m-d" "2024-01-15"))
(DateTime/createFromFormat "Y-m-d" "2024-01-15")

;; Static constants/properties: php/:: or the bare Class/MEMBER shorthand
(php/:: \DateTimeImmutable ATOM)  ; => "Y-m-d\\TH:i:sP"
\DateTimeImmutable/ATOM
```

### Output parameters (by reference)

Some PHP methods write into an argument (`PDOStatement::bindColumn`, `bindParam`). Wrap the local in `php/ref` so the result is observable from Phel:

```phel
(let [out "INIT"]
  (php/-> stmt (bindColumn 1 (php/ref out) \PDO/PARAM_STR))
  (php/-> stmt (fetch \PDO/FETCH_BOUND))
  out)                              ; => the fetched column value
```

`php/ref` takes a local binding and works in `php/->`/`php/::` calls.

### Working with PHP Arrays

```phel
;; Constructors
(def php-arr (php/array 1 2 3))                  ; indexed PHP array
(php-indexed-array 1 2 3)                         ; same
(def assoc-arr (php-associative-array "name" "Alice" "age" 30))

;; `#php` reader literal (non-recursive): next vector/map becomes a PHP array
#php [1 2 3]                                      ; => (php-indexed-array 1 2 3)
#php {"name" "Alice" "age" 30}                    ; => (php-associative-array "name" "Alice" "age" 30)

;; Access and mutate (aset mutates in place)
(php/aget assoc-arr "name")       ; => "Alice"
(php/aset php-arr 0 99)            ; modifies php-arr in place
```

### Namespaces and Imports

Use `:use` for PHP classes, then reference them without a namespace prefix:

```phel
(ns app.services
  (:use PDO)                                          ; single class
  (:use DateTime DateInterval)                        ; multiple
  (:use Symfony\Component\HttpFoundation\Request))

(php/new PDO "mysql:host=localhost" "user" "pass")
(php/new Request)
```

### Requiring Phel Namespaces

`:require` accepts both list-style and vector entries (works in `.phel` and `.cljc`):

```phel
;; List entries
(ns app.services
  (:require phel.string :as str)
  (:require phel.json :as json :refer [encode decode]))

;; Vector entries, same meaning
(ns app.services
  (:require [phel.string :as str]
            [phel.json :as json :refer [encode decode]]))
```

Phel uses `.` as the namespace separator, matching Clojure. The legacy `\` separator still parses but is deprecated; new code should use `.`.

## Type Conversions

### Phel to PHP

```phel
(to-php-array [1 2 3])            ; => PHP [1, 2, 3]
(to-php-array {:a 1 :b 2})        ; => PHP ["a" => 1, "b" => 2]
(name :keyword)                   ; keyword => "keyword"
(phel->php {1 "a" "b" 2})         ; => PHP [1 => "a", "b" => 2]  (recursive; any int/string key)
```

### PHP to Phel

```phel
(php-array-to-map php-assoc-array)   ; => {:key value}
(vals php-indexed-array)             ; => [val1 val2 val3]
(php->phel php-array)                ; => recursive: indexed => vector, assoc => map
```

### Maps and objects

```phel
(hydrate "App\\Dto\\Product" {:id 1})   ; map => instance (constructor bypassed)
(bean obj)                              ; public properties => {:key value}
```

## Calling Phel from PHP

### Basic Example

**hello.phel:**
```phel
(ns app.hello)

(defn greet [name] (str "Hello, " name "!"))
(defn add [a b] (+ a b))
```

**index.php:**
```php
<?php
require 'vendor/autoload.php';

// Bootstrap Phel and load the namespace (compiles on first call).
\Phel::run(__DIR__, 'app.hello');

// getDefinition returns the registered fn by name.
$greet = \Phel::getDefinition('app.hello', 'greet');
echo $greet('World'); // => "Hello, World!"
```

### Web Application Example

**routes.phel:**
```phel
(ns app.routes
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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

\Phel::run(__DIR__, 'app.routes');

$request = Request::createFromGlobals();

$home = \Phel::getDefinition('app.routes', 'handle-home');
$api  = \Phel::getDefinition('app.routes', 'handle-api');

$response = match ($request->getPathInfo()) {
    '/'    => $home($request),
    '/api' => $api($request),
    default => new Response('Not Found', 404),
};

$response->send();
```

## Error Handling

```phel
(ns app.db
  (:use PDO PDOException InvalidArgumentException))

;; Catch PHP exceptions
(defn connect [dsn user pass]
  (try
    (php/new PDO dsn user pass)
    (catch PDOException e
      (println "Database error:" (php/-> e (getMessage)))
      nil)))

;; Throw PHP exceptions
(defn validate-age [age]
  (when (< age 0)
    (throw (php/new InvalidArgumentException "Age cannot be negative")))
  age)
```

## Performance Tips

```phel
;; Transients: mutable during batch build, then freeze
(persistent!
  (reduce (fn [acc x] (conj acc (* x 2))) (transient []) large-list))

;; Prefer PHP's optimized functions for heavy lifting on PHP arrays
(php/array_map #(* % 2) php-array)

;; Avoid unnecessary conversions: stay in PHP when the data is a PHP array
(php/array_map inc php-data)         ; not (to-php-array (map inc (php-array-to-map php-data)))
```

## Common Patterns

### Database Access

```phel
(ns app.db
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
(ns app.http
  (:require phel.json :as json))

(defn fetch-json [url]
  (let [response (php/file_get_contents url)
        data (php/json_decode response true)]
    (php-array-to-map data)))

(defn post-json [url data]
  (let [opts (php-associative-array
               "http" (php-associative-array
                        "method" "POST"
                        "header" "Content-Type: application/json"
                        "content" (json/encode data)))
        context (php/stream_context_create opts)]
    (php/file_get_contents url false context)))
```

### File Operations

```phel
(ns app.files)

(defn read-lines [filename]
  (php/explode "\n" (php/file_get_contents filename)))

(defn write-lines [filename lines]
  (php/file_put_contents filename (php/implode "\n" (to-php-array lines))))
```

## Tips for PHP Developers

- **Immutability**: collections don't mutate. `(conj vec item)` returns a *new* vector.
- **No `$` sigil**: variables don't need `$`.
- **Keywords**: use `:keyword` for map keys.
- **Truthiness**: only `false` and `nil` are falsy (not `0` or `""`).
- **Parens**: `(func arg)` calls; `func` is the value.
- **PHP arrays**: use directly or convert to Phel collections.

## Tips for Clojure Developers

- **PHP interop**: use the `php/` prefix (not `.` or `..`).
- **Method calls**: `(php/-> obj (method))` (also `(.method obj)`).
- **Deref**: `@my-atom` shortcuts `(deref my-atom)`.
- **Import classes**: `:use` in `ns`, not `:import`.
- **Require vectors**: `(:require [phel.string :as str :refer [upper-case]])` works alongside the list form.
- **Namespace separators**: Phel uses `.` matching Clojure; legacy `\` is deprecated.
- **Reader conditionals**: `#?(:phel ...)` and `#?@(:phel ...)` for `.cljc` files.
- **Unquote**: `~` and `~@` inside syntax-quote (`,` / `,@` deprecated).
- **Auto-gensym**: `name#` inside syntax-quote produces a unique symbol (`name$` deprecated).
- **Macro env**: `&form` and `&env` are implicit in every `defmacro`. `&env` is a map of in-scope locals keyed by symbol. `(:ns &env)` is always `nil`, so the `.cljc` `(if (:ns &env) "cljs" ...)` trick lands on the non-cljs branch.
- **Lambda syntax**: `#(+ %1 %2)` recommended; `|(+ $1 $2)` deprecated.

## See Also

- [Examples](examples/README.md)
- [Reader Shortcuts](reader-shortcuts.md)
- [Common Patterns](patterns.md)

---

📖 **Full guide:** [PHP Interop on phel-lang.org](https://phel-lang.org/documentation/php-interop/)

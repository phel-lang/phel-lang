# Quick Start Tutorial

Get up and running with Phel in 5 minutes.

## Installation

### Option 1: Scaffold a new project

```bash
composer require phel-lang/phel-lang
./vendor/bin/phel init
```

`phel init` creates a `phel-config.php`, a starter `src/phel/main.phel`, and a matching `tests/phel/main_test.phel` so you can run, test, and REPL immediately.

For a single-file experiment or sandbox, use the root layout:

```bash
./vendor/bin/phel init --minimal
```

This creates `main.phel`, `main_test.phel`, and a one-line `phel-config.php` at the repo root, no subdirectories.

Useful `init` flags:

| Flag | Purpose |
|------|---------|
| `--minimal`, `-m` | Root layout: single `main.phel` at the project root |
| `--flat`, `-f` | Flat layout: `src/` and `tests/` instead of `src/phel/` and `tests/phel/` |
| `--no-tests` | Skip generating the matching test file |
| `--no-gitignore` | Skip `.gitignore` creation |
| `--dry-run` | Preview without writing anything |
| `--force` | Overwrite existing files |

### Option 2: Add to an existing PHP project manually

If `phel init` doesn't fit your project structure, write `phel-config.php` by hand:

```php
<?php

use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;

// Zero-config: auto-detects layout + namespace
return PhelConfig::forProject();

// Or, explicit:
// return PhelConfig::forProject('my-app\main', ProjectLayout::Flat);
```

## Your First Phel Code

### 1. Hello World (2 minutes)

Create `src/hello.phel`:

```phel
(ns app\hello)

(defn greet [name]
  (str "Hello, " name "!"))

;; Call the function
(println (greet "World"))
```

Run it:
```bash
./vendor/bin/phel run src/hello.phel
# => Hello, World!
```

Or evaluate a snippet without a file:
```bash
./vendor/bin/phel eval '(println "Hello, World!")'
echo '(println "from stdin")' | ./vendor/bin/phel eval -
./vendor/bin/phel eval - < src/hello.phel
```

### 2. Interactive REPL (1 minute)

Start the REPL:
```bash
./vendor/bin/phel repl
```

Try it out:
```phel
Welcome to the Phel Repl (v0.34.0)
Type "exit" or press Ctrl-D to exit.
user:1> (+ 1 2 3)
6
user:2> (map #(* % 2) [1 2 3 4 5])
[2 4 6 8 10]
user:3> (def numbers [10 20 30 40])
#'user/numbers
user:4> (filter #(> % 25) numbers)
[30 40]
user:5> (/ 1 0)
; ...error...
user:6> *e                        ; last exception
```

The prompt shows the current namespace (it switches on `(ns ...)` / `(in-ns ...)`). `*1`, `*2`, `*3` hold the last three REPL results; `*e` the last exception. Type `(exit)` or press Ctrl-D to quit.

### 3. Working with Collections (1 minute)

```phel
;; Vectors (immutable)
(def fruits ["apple" "banana" "cherry"])
(get fruits 1)                    ; => "banana"
(count fruits)                    ; => 3
(conj fruits "date")              ; => ["apple" "banana" "cherry" "date"]

;; Maps
(def user {:name "Alice" :age 30 :city "NYC"})
(get user :name)                  ; => "Alice"
(assoc user :email "alice@example.com")
;; => {:name "Alice" :age 30 :city "NYC" :email "alice@example.com"}

;; Sets
(def tags #{:clojure :php :lisp})
(contains? tags :php)             ; => true

;; PHP arrays via #php reader literal
(def indexed #php [1 2 3])             ; PHP indexed array
(def assoc   #php {"a" 1 "b" 2})       ; PHP associative array

;; Tagged literals
(def slug-rx #regex "^[a-z0-9-]+$")    ; PCRE pattern string

;; `#inst` reads ISO-8601 into \DateTimeImmutable at read time. Use in let/fn,
;; not `def` (persistent defs require TypeInterface or scalar).
(let [released #inst "2026-04-20T00:00:00Z"]
  (.format released "Y-m-d"))          ; => "2026-04-20"
```

### 4. Functions and Higher-Order Functions (1 minute)

```phel
;; Define a function
(defn square [x]
  (* x x))

(square 5)                        ; => 25

;; Anonymous functions
#(* % %)                          ; Short syntax
(fn [x] (* x x))                  ; Long syntax

;; Map, filter, reduce
(map square [1 2 3 4])            ; => [1 4 9 16]
(filter even? [1 2 3 4 5 6])      ; => [2 4 6]
(reduce + 0 [1 2 3 4])            ; => 10

;; Threading macros for readable pipelines
(->> [1 2 3 4 5 6]
     (filter even?)
     (map square)
     (reduce +))                  ; => 56

;; Function composition
(def double #(* % 2))
(def add-ten #(+ % 10))
(def process (comp add-ten double))

(process 5)                       ; => 20 (5 * 2 + 10)
```

### 5. Pattern Matching (1 minute)

```phel
(ns app\routing
  (:require phel\match :refer [match]))

(defn route [req]
  (match [req]
    [{:method "GET"  :path "/"}]              :home
    [{:method "GET"  :path ["users" id]}]     [:show-user id]
    [{:method "POST" :path "/login"}]         :login
    :else                                     :not-found))

(route {:method "GET" :path "/"})               ; => :home
(route {:method "GET" :path ["users" "42"]})    ; => [:show-user "42"]
```

See [Pattern Matching Guide](match-guide.md) for guards, destructuring, and more patterns.

## First Web Application (5 minutes)

Build an API endpoint using the interop shortcuts.

### Step 1: Install Dependencies

```bash
composer require symfony/http-foundation
```

### Step 2: Create Route Handler

Create `src/routes.phel`:

```phel
(ns app\routes
  (:use Symfony\Component\HttpFoundation\Response)
  (:require phel\json :as json))

(defn- html-response [body status]
  (new Response body status #php {"Content-Type" "text/html"}))

(defn- json-response [data status]
  (new Response (json/encode data) status #php {"Content-Type" "application/json"}))

(defn handle-home [_request]
  (html-response "<h1>Welcome to Phel!</h1>" 200))

(defn handle-greet [_request name]
  (html-response (str "Hello, " name "!") 200))

(defn handle-api [_request]
  (json-response {:status    "ok"
                  :message   "Phel API is running"
                  :timestamp (php/time)} 200))

(defn handle-users [_request]
  (json-response [{:id 1 :name "Alice" :email "alice@example.com"}
                  {:id 2 :name "Bob"   :email "bob@example.com"}] 200))
```

`(new Response ...)` and `#php {...}` are shortcuts for `(php/new Response ...)` and `(php-associative-array ...)`. They compile identically.

### Step 3: Create PHP Entry Point

Create `public/index.php`:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Boot the Phel runtime and load the routes namespace.
\Phel::bootstrap(__DIR__ . '/..');
\Phel::run(__DIR__ . '/..', 'app\\routes');

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// Resolve Phel functions as callables. Namespace hyphens become underscores.
$call = static fn (string $fn, mixed ...$args) =>
    \Phel::getDefinition('app_routes', $fn)(...$args);

$response = match (true) {
    $path === '/'      => $call('handle-home',  $request),
    $path === '/api'   => $call('handle-api',   $request),
    $path === '/users' => $call('handle-users', $request),
    (bool) preg_match('#^/greet/(\w+)$#', $path, $m)
                       => $call('handle-greet', $request, $m[1]),
    default            => new Response('Not Found', 404),
};

$response->send();
```

`\Phel::bootstrap()` sets up the runtime, `\Phel::run()` compiles and loads a namespace, and `\Phel::getDefinition()` resolves a Phel function as a PHP callable. No facade wiring, no DI container.

### Step 4: Run the Server

```bash
php -S localhost:8000 -t public
```

Test the endpoints:
```bash
curl http://localhost:8000/
curl http://localhost:8000/greet/Alice
curl http://localhost:8000/api
curl http://localhost:8000/users
```

## Common Tasks

### Read and Process a File

```phel
(ns app\files
  (:require phel\string :as str))

(defn read-csv [filename]
  (let [content (php/file_get_contents filename)
        lines   (str/split content #"\n")]
    (map #(str/split % #",") lines)))

(defn process-users [filename]
  (let [rows    (read-csv filename)
        headers (first rows)
        data    (rest rows)]
    (map (fn [row] (zipmap (map keyword headers) row))
         data)))

;; Usage
(process-users "users.csv")
;; => [{:id "1" :name "Alice" :email "alice@example.com"}
;;     {:id "2" :name "Bob"   :email "bob@example.com"}]
```

### Work with Dates

```phel
(ns app\dates
  (:use DateTime DateInterval))

(defn format-date [dt fmt]
  (.format dt fmt))

(defn add-days [dt days]
  (.add dt (new DateInterval (str "P" days "D"))))

(defn days-between [d1 d2]
  (.-days (.diff d1 d2)))

;; `#inst` reads ISO-8601 into \DateTimeImmutable at read time, no runtime cost.
;; Keep such values local; persistent `def` is reserved for scalars/TypeInterface.
(let [launch   #inst "2026-04-20T00:00:00Z"
      tomorrow (add-days launch 1)]
  (format-date tomorrow "Y-m-d"))      ; => "2026-04-21"
```

`.method`, `.-field`, and `(new ClassName ...)` are shorthands for `php/->` and `php/new`. Both styles compile to identical PHP.

### Database Queries

```phel
(ns app\database
  (:use PDO))

(defn connect []
  (new PDO "sqlite::memory:"))

(defn fetch-all [pdo sql params]
  (let [stmt (.prepare pdo sql)]
    (.execute stmt (to-php-array params))
    (map php-array-to-map (.fetchAll stmt))))

(defn find-user [pdo email]
  (first (fetch-all pdo "SELECT * FROM users WHERE email = ?" [email])))

(defn create-user [pdo user]
  (let [stmt (.prepare pdo "INSERT INTO users (name, email) VALUES (?, ?)")]
    (.execute stmt (to-php-array [(:name user) (:email user)]))))
```

## Next Steps

### Learn More

- **[Common Patterns](patterns.md)**: idiomatic Phel including pattern matching
- **[Data Structures Guide](data-structures-guide.md)**: vectors, maps, sets, transients
- **[PHP Interop](php-interop.md)**: PHP integration and shortcuts
- **[Reader Shortcuts](reader-shortcuts.md)**: syntax reference, `#inst`, `#regex`, `#php`
- **[Async Guide](async-guide.md)**: concurrency with fibers and AMPHP
- **[CLI Guide](cli-guide.md)**: build CLIs with `phel\cli`
- **[Schema Guide](schema-guide.md)**: data-driven validation, coercion, generation
- **[Pattern Matching Guide](match-guide.md)**: `match` with guards and destructuring
- **[Linter Guide](lint-guide.md)**: `phel lint` rules and configuration
- **[Language Server Guide](lsp-guide.md)**: `phel lsp` editor integration
- **[nREPL Guide](nrepl-guide.md)**: `phel nrepl` for editor tooling
- **[Watch Guide](watch-guide.md)**: `phel watch` hot-reload
- **[Framework Integration](framework-integration.md)**: Laravel, Symfony, framework-less
- **[Performance Tips](performance.md)**: Opcache CLI setup, cache reset
- **[Examples](examples/)**: runnable single-file samples

### REPL Workflow

Load your code in the REPL:
```phel
user:1> (require 'app\hello)
user:2> (in-ns 'app\hello)
app\hello:3> (greet "REPL")
"Hello, REPL!"
```

Get documentation and inspect symbols:
```phel
app\hello:4> (doc map)
app\hello:5> (doc filter)
app\hello:6> (resolve 'map)        ; => #'phel\core/map
```

### Testing Your Code

Create `tests/hello_test.phel`:

```phel
(ns tests\hello-test
  (:require phel\test :refer [deftest is])
  (:require app\hello :refer [greet]))

(deftest test-greet
  (is (= "Hello, World!" (greet "World")))
  (is (= "Hello, Alice!" (greet "Alice"))))

;; Tag a slow or integration test so it can be included/excluded at the CLI.
(deftest ^{:tags [:integration]} test-external-api
  (is (= 200 (:status (fetch-remote)))))
```

Run tests:
```bash
./vendor/bin/phel test                         # all tests
./vendor/bin/phel test --filter greet          # substring/regex over test names
./vendor/bin/phel test --exclude integration   # skip tagged tests
./vendor/bin/phel test --ns 'app.*'            # namespace glob
./vendor/bin/phel test --reporter=testdox      # also: dot, tap, junit-xml
```

## Tips for Getting Started

**For PHP Developers:**
- Think immutable: functions return new values, don't modify arguments
- Recursion: use `loop`/`recur` instead of `for`/`while`
- Threading macros: `->` and `->>` for readable pipelines
- Maps beat classes for most data
- Prefer interop shortcuts (`.method`, `(new Class)`, `#php [...]`)

**For Clojure Developers:**
- PHP interop uses `php/` prefix; shortcuts `.method`, `.-field`, `ClassName/method`, `(new Class)` also work
- Import PHP classes with `:use` in `ns`, not `:import`
- Regex literals are `#"pattern"`; `#regex "..."` reads as a delimited PCRE string
- Some function names differ (see [Clojure Migration](clojure-migration.md))

## Common Gotchas

```phel
;; Wrong: forgot parens for function call
map square [1 2 3]              ; Error: not a function

;; Right
(map square [1 2 3])            ; => [1 4 9]

;; Wrong: trying to mutate
(def nums [1 2 3])
(conj nums 4)                   ; Returns NEW vector
nums                            ; Still [1 2 3]!

;; Right: rebind or use result
(def nums [1 2 3])
(def nums (conj nums 4))        ; Rebind to new vector
nums                            ; => [1 2 3 4]
```

Happy coding.

# Quick Start Tutorial

Get up and running with Phel in 5 minutes.

## Installation

### Option 1: Quick Start with Composer

```bash
composer create-project phel-lang/phel-lang my-app
cd my-app
```

### Option 2: Add to Existing PHP Project

```bash
composer require phel-lang/phel-lang
```

Create `phel-config.php`:
```php
<?php

return [
    'src-dirs' => ['src'],
    'test-dirs' => ['tests'],
    'out' => 'out',
    'vendor-dir' => 'vendor',
    'export' => [
        'directories' => ['src'],
        'namespace-prefix' => 'PhelGenerated',
        'target-directory' => 'src/PhelGenerated',
    ],
];
```

## Your First Phel Code

### 1. Hello World (2 minutes)

Create `src/hello.phel`:

```phel
(ns app\hello)

(defn greet [name]
  (str "Hello, " name "!"))

# Call the function
(println (greet "World"))
```

Run it:
```bash
./vendor/bin/phel run src/hello.phel
# => Hello, World!
```

### 2. Interactive REPL (1 minute)

Start the REPL:
```bash
./vendor/bin/phel repl
```

Try it out:
```phel
phel:1> (+ 1 2 3)
6

phel:2> (map |(* $ 2) [1 2 3 4 5])
[2 4 6 8 10]

phel:3> (def numbers [10 20 30 40])
numbers

phel:4> (filter |(> $ 25) numbers)
[30 40]
```

Type `(exit)` or press Ctrl+D to quit.

### 3. Working with Collections (1 minute)

```phel
# Vectors (like PHP arrays)
(def fruits ["apple" "banana" "cherry"])
(get fruits 1)                    # => "banana"
(count fruits)                    # => 3
(push fruits "date")              # => ["apple" "banana" "cherry" "date"]

# Maps (associative arrays)
(def user {:name "Alice" :age 30 :city "NYC"})
(get user :name)                  # => "Alice"
(assoc user :email "alice@example.com")
# => {:name "Alice" :age 30 :city "NYC" :email "alice@example.com"}

# Sets (unique values)
(def tags #{:clojure :php :lisp})
(contains? tags :php)             # => true
```

### 4. Functions and Higher-Order Functions (1 minute)

```phel
# Define a function
(defn square [x]
  (* x x))

(square 5)                        # => 25

# Anonymous functions
|(* $ $)                          # Short syntax
(fn [x] (* x x))                  # Long syntax

# Map, filter, reduce
(map square [1 2 3 4])            # => [1 4 9 16]
(filter even? [1 2 3 4 5 6])      # => [2 4 6]
(reduce + 0 [1 2 3 4])            # => 10

# Function composition
(def double |(* $ 2))
(def add-ten |(+ $ 10))
(def process (comp add-ten double))

(process 5)                       # => 20 (5 * 2 + 10)
```

## First Web Application (5 minutes)

Let's build a simple API endpoint.

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

(defn handle-home [request]
  (php/new Response
    "<h1>Welcome to Phel!</h1>"
    200
    (php-associative-array "Content-Type" "text/html")))

(defn handle-greet [request name]
  (let [greeting (str "Hello, " name "!")]
    (php/new Response greeting 200)))

(defn handle-api [request]
  (let [data {:status "ok"
              :message "Phel API is running"
              :timestamp (php/time)}
        json-str (json/encode data)]
    (php/new Response
      json-str
      200
      (php-associative-array "Content-Type" "application/json"))))

(defn handle-users [request]
  (let [users [{:id 1 :name "Alice" :email "alice@example.com"}
               {:id 2 :name "Bob" :email "bob@example.com"}]
        json-str (json/encode users)]
    (php/new Response
      json-str
      200
      (php-associative-array "Content-Type" "application/json"))))
```

### Step 3: Create PHP Entry Point

Create `public/index.php`:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Phel\Run\RunFacade;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

RunFacade::initialize(__DIR__ . '/../');

$request = Request::createFromGlobals();
$path = $request->getPathInfo();

// Simple routing
$response = match (true) {
    $path === '/' =>
        \Phel::callPhel('app\routes', 'handle-home', $request),

    $path === '/api' =>
        \Phel::callPhel('app\routes', 'handle-api', $request),

    $path === '/users' =>
        \Phel::callPhel('app\routes', 'handle-users', $request),

    preg_match('#^/greet/(\w+)$#', $path, $matches) =>
        \Phel::callPhel('app\routes', 'handle-greet', $request, $matches[1]),

    default =>
        new Response('Not Found', 404),
};

$response->send();
```

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
  (:require phel\str :as str))

(defn read-csv [filename]
  (let [content (php/file_get_contents filename)
        lines (str/split content #"\n")]
    (map |(str/split $ #",") lines)))

(defn process-users [filename]
  (let [rows (read-csv filename)
        headers (first rows)
        data (rest rows)]
    (map (fn [row]
           (zipmap (map keyword headers) row))
         data)))

# Usage
(process-users "users.csv")
# => [{:id "1" :name "Alice" :email "alice@example.com"}
#     {:id "2" :name "Bob" :email "bob@example.com"}]
```

### Work with Dates

```phel
(ns app\dates
  (:use DateTime DateInterval DateTimeZone))

(defn format-date [timestamp format]
  (let [dt (php/new DateTime)]
    (php/-> dt (setTimestamp timestamp))
    (php/-> dt (format format))))

(defn add-days [date days]
  (let [interval (php/new DateInterval (str "P" days "D"))]
    (php/-> date (add interval))))

(defn days-between [date1 date2]
  (let [diff (php/-> date1 (diff date2))]
    (php/-> diff -days)))

# Usage
(def now (php/new DateTime))
(def tomorrow (add-days now 1))
(format-date (php/time) "Y-m-d H:i:s")
```

### Database Queries

```phel
(ns app\database
  (:use PDO))

(defn connect []
  (php/new PDO "sqlite::memory:"))

(defn fetch-all [pdo sql params]
  (let [stmt (php/-> pdo (prepare sql))]
    (php/-> stmt (execute (to-php-array params)))
    (map php-array-to-map
         (php/-> stmt (fetchAll)))))

(defn find-user [pdo email]
  (let [results (fetch-all pdo
                  "SELECT * FROM users WHERE email = ?"
                  [email])]
    (first results)))

(defn create-user [pdo user]
  (let [stmt (php/-> pdo (prepare
                "INSERT INTO users (name, email) VALUES (?, ?)"))]
    (php/-> stmt (execute (to-php-array [(get user :name)
                                          (get user :email)])))))
```

## Next Steps

### Learn More

- **[Common Patterns](patterns.md)** - Idiomatic Phel code
- **[PHP Interop](php-interop.md)** - Deep dive into PHP integration
- **[Reader Shortcuts](reader-shortcuts.md)** - Syntax reference
- **[Examples](examples/)** - More code samples

### REPL Workflow

Load your code in the REPL:
```phel
phel:1> (require 'app\hello)
phel:2> (in-ns 'app\hello)
phel:3> (greet "REPL")
"Hello, REPL!"
```

Get documentation:
```phel
phel:1> (doc map)
phel:2> (doc filter)
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
```

Run tests:
```bash
./vendor/bin/phel test
```

## Tips for Getting Started

**For PHP Developers:**
- Think immutable: functions return new values, don't modify arguments
- Embrace recursion: use `loop`/`recur` instead of `for`/`while`
- Use threading macros: `->` and `->>` for readable pipelines
- Maps are better than classes for most data

**For Clojure Developers:**
- PHP interop uses `php/` prefix (not `.` or `..`)
- Import PHP classes with `:use` in namespace
- No reader literals for regex, use `#"pattern"`
- Some Clojure functions have different names (check docs)

## Common Gotchas

```phel
# Wrong - forgot parens for function call
map square [1 2 3]              # Error: not a function

# Right
(map square [1 2 3])            # => [1 4 9]

# Wrong - trying to mutate
(def nums [1 2 3])
(push nums 4)                   # Returns NEW vector
nums                            # Still [1 2 3]!

# Right - rebind or use result
(def nums [1 2 3])
(def nums (push nums 4))        # Rebind to new vector
nums                            # => [1 2 3 4]
```

Happy coding! 🎉

# Quick Start

Up and running with Phel in five minutes.

## Install

```bash
composer require phel-lang/phel-lang
./vendor/bin/phel init
```

`phel init` writes `phel-config.php`, `src/phel/main.phel`, and `tests/phel/main_test.phel`. Add `--minimal` for a single-file root layout, `--flat` to drop the `phel/` subdirectory, `--dry-run` to preview.

Already have a PHP project? Write `phel-config.php` by hand:

```php
<?php

use Phel\Config\PhelConfig;

return PhelConfig::forProject();          // auto-detects layout and namespace
```

## Hello World

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

## REPL

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

## Collections

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

## Functions

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

## Pattern Matching

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

## Calling Phel from PHP

`src/greet.phel`:

```phel
(ns app\greet)

(defn hello [name] (str "Hello, " name "!"))
```

`public/index.php`:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

\Phel::bootstrap(__DIR__ . '/..');
\Phel::run(__DIR__ . '/..', 'app\\greet');

echo \Phel::getDefinition('app_greet', 'hello')('World');
```

`getDefinition` resolves any Phel function as a PHP callable. Namespace hyphens become underscores.

For a full HTTP example see [Framework Integration](framework-integration.md).

## Tests

`tests/hello_test.phel`:

```phel
(ns tests\hello-test
  (:require phel\test :refer [deftest is])
  (:require app\hello :refer [greet]))

(deftest test-greet
  (is (= "Hello, World!" (greet "World"))))
```

```bash
./vendor/bin/phel test                       # all tests
./vendor/bin/phel test --filter greet        # name regex
./vendor/bin/phel test --reporter=testdox    # also: dot, tap, junit-xml
```

Tag tests with `^{:tags [:integration]}` and select via `--include`/`--exclude`.

## REPL Workflow

```phel
user:1> (require 'app\hello)
user:2> (in-ns 'app\hello)
app\hello:3> (greet "REPL")        ; => "Hello, REPL!"
app\hello:4> (doc map)             ; show docs
app\hello:5> (resolve 'map)        ; => #'phel\core/map
```

## Common Gotchas

```phel
;; Functions need parens
map square [1 2 3]              ; Error: not a function
(map square [1 2 3])            ; => [1 4 9]

;; Values are immutable; conj returns a new vector
(def nums [1 2 3])
(conj nums 4)                   ; => [1 2 3 4]
nums                            ; => [1 2 3]
(def nums (conj nums 4))        ; rebind to keep the new value
```

PHP devs: prefer `loop`/`recur` over `for`/`while`, threading macros (`->`, `->>`) for pipelines, maps over classes.

Clojure devs: PHP interop uses `php/` prefix or shortcuts (`.method`, `.-field`, `ClassName/method`, `(new Class)`); import PHP classes with `:use` not `:import`. See [Clojure Migration](clojure-migration.md) for naming differences.

## Next Steps

Full guide index: [docs/README.md](README.md).

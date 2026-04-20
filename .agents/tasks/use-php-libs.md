# Use a PHP library

`composer require <vendor/pkg>`, `(:use Fully\Qualified\Name)`, call via interop.

## Syntax

| Task | Phel |
|------|------|
| Instantiate | `(php/new Class args)` / `(Class.)` |
| Method | `(.method obj args)` / `(php/-> obj (method args))` |
| Static | `(Class/method args)` / `(php/:: Class (method args))` |
| Constant | `Class/CONST` |
| Property | `(.-prop obj)` |
| Fn (global) | `(php/strlen s)` |
| Fn (namespaced) | `(php/Amp\trapSignal xs)` |
| PHP array indexed | `#php [1 2 3]` |
| PHP array assoc | `#php {"k" "v"}` |
| Catch | `(catch php\SomeException e ...)` |

## Guzzle

```bash
composer require guzzlehttp/guzzle
```

```phel
(ns my-app\http
  (:use GuzzleHttp\Client)
  (:require phel\json :as json))

(def client
  (php/new Client
    #php {"base_uri" "https://api.example.com/" "timeout" 5.0}))

(defn fetch-user [id]
  (let [resp (.request client "GET" (str "/users/" id))]
    (json/decode (php/strval (.getBody resp)))))
```

## PDO

```phel
(ns my-app\db
  (:use PDO PDOException))

(defn connect [dsn user pass]
  (try
    (php/new PDO dsn user pass
      #php {PDO/ATTR_ERRMODE PDO/ERRMODE_EXCEPTION})
    (catch PDOException e (println "fail:" (.getMessage e)) nil)))

(defn query-all [pdo sql params]
  (let [stmt (.prepare pdo sql)]
    (.execute stmt (to-php-array params))
    (map php-array-to-map (.fetchAll stmt PDO/FETCH_ASSOC))))
```

## Type conversions

| Direction | Call |
|-----------|------|
| Phel map → PHP assoc | `(to-php-array {:a 1})` |
| Phel vec → PHP indexed | `(to-php-array [1 2])` |
| Keyword → string | `(name :foo)` |
| PHP assoc → Phel map | `(php-array-to-map arr)` |

## Shortcuts

```phel
(def trap-signal php/\Amp\trapSignal)
(trap-signal [php/SIGINT php/SIGTERM])
```

## Gotchas

- Convert maps for PHP libs; use `#php {}` or `to-php-array`.
- Method chain: `(php/-> obj (a) (b))`, not `(-> obj .a .b)`.
- Static constants need `:use` or full path.
- PHP warnings don't throw; `(php/error_get_last)` to check.
- Hot loops: prefer `php/array_map` over round-tripping.

## Next

`docs/php-interop.md`, `docs/framework-integration.md`

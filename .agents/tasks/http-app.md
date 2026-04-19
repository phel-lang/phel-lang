# Task: Build HTTP app in Phel

## Goal

Phel handles routes. PHP entry point dispatches requests. Runs on `php -S` or any PHP server.

## Approach

Two layers:

1. **PHP entry** (`public/index.php`) — boots Phel, reads request, dispatches.
2. **Phel handlers** (`src/phel/routes.phel`) — one fn per route, returns `Symfony\Component\HttpFoundation\Response`.

## Steps

### 1. Scaffold + install Symfony HTTP foundation

```bash
./vendor/bin/phel init my-api
cd my-api
composer require symfony/http-foundation
```

### 2. Route handlers

`src/phel/routes.phel`:

```phel
(ns my-api\routes
  (:use Symfony\Component\HttpFoundation\Response)
  (:require phel\json :as json))

(defn- json-response [data status]
  (php/new Response
    (json/encode data)
    status
    (php-associative-array "Content-Type" "application/json")))

(defn handle-home [_request]
  (php/new Response "<h1>Hello from Phel</h1>" 200))

(defn handle-health [_request]
  (json-response {:status "ok" :ts (php/time)} 200))

(defn handle-greet [_request name]
  (json-response {:message (str "Hello, " name "!")} 200))
```

### 3. PHP entry point

`public/index.php`:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Phel\Run\RunFacade;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

RunFacade::initialize(__DIR__ . '/../');

$request = Request::createFromGlobals();
$path = $request->getPathInfo();

$response = match (true) {
    $path === '/'        => \Phel::callPhel('my-api\routes', 'handle-home', $request),
    $path === '/health'  => \Phel::callPhel('my-api\routes', 'handle-health', $request),
    (bool) preg_match('#^/greet/(\w+)$#', $path, $m)
                         => \Phel::callPhel('my-api\routes', 'handle-greet', $request, $m[1]),
    default              => new Response('Not Found', 404),
};

$response->send();
```

### 4. Run

```bash
php -S localhost:8000 -t public
```

Test:

```bash
curl http://localhost:8000/
curl http://localhost:8000/health
curl http://localhost:8000/greet/Alice
```

### 5. Tests

`tests/phel/routes_test.phel`:

```phel
(ns tests\routes-test
  (:require phel\test :refer [deftest is])
  (:require phel\json :as json)
  (:require my-api\routes :refer [handle-health handle-greet]))

(deftest health-returns-ok
  (let [resp (handle-health nil)
        body (php/-> resp (getContent))
        data (json/decode body)]
    (is (= "ok" (get data :status)))))

(deftest greet-contains-name
  (let [resp (handle-greet nil "Alice")
        body (php/-> resp (getContent))]
    (is (php/str_contains body "Alice"))))
```

Run: `./vendor/bin/phel test`.

## Production

For AOT compilation (compile once on deploy, zero compile per request):

```bash
./vendor/bin/phel build
```

Then replace `\Phel::callPhel(...)` path with `require 'build/<ns>/boot.php'` — see `docs/framework-integration.md`.

## Gotchas

- `json/encode` accepts Phel maps directly; no need to convert via `to-php-array` first.
- `php-associative-array "k" "v"` for PHP assoc arrays; `#php {"k" "v"}` reader literal is equivalent.
- Don't add top-level side effects — guard with `(when-not *build-mode* ...)` if you must call side-effecting code at load.
- Unused request arg convention: prefix with `_` (`_request`).

## Next

- Routing library: consider `phel\http` (core) or bring a PHP router (FastRoute, Symfony Routing)
- Framework integration: `docs/framework-integration.md` (Symfony, Laravel)
- DB access: `docs/php-interop.md` (§ Database Access)

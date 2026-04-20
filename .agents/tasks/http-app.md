# HTTP app

Bundled `phel\router` + `phel\http` + `phel\json`. Runnable: `.agents/examples/todo-app/`.

## Handlers

`src/handlers.phel`:

```phel
(ns my-api\handlers
  (:require phel\http :as h)
  (:require phel\json :as json))

(defn- json-response [status data]
  (h/response-from-map
    {:status status
     :headers {:content-type "application/json"}
     :body (json/encode data)}))

(defn home   [_req] (h/response-from-map {:status 200 :body "<h1>Phel</h1>"}))
(defn health [_req] (json-response 200 {:status "ok" :ts (php/time)}))
(defn greet  [req]
  (let [name (get-in req [:attributes :match :path-params :name] "World")]
    (json-response 200 {:message (str "Hello, " name "!")})))
```

## Routes

`src/routes.phel`:

```phel
(ns my-api\routes
  (:require phel\router :as r)
  (:require my-api\handlers :as h))

(def app-router
  (r/router
    [["/"             {:get {:handler h/home}}]
     ["/health"       {:get {:handler h/health}}]
     ["/greet/{name}" {:get {:handler h/greet}}]]))

(def app-handler (r/handler app-router))
```

Data keys: `:handler` (any-method), `:get`/`:post`/... (per-method `{:handler :middleware}`), `:middleware`, `:name`.

## Entry

`src/entry.phel`:

```phel
(ns my-api\entry
  (:require phel\http :as h)
  (:require my-api\routes :refer [app-handler]))

(when-not *build-mode*
  (-> (h/request-from-globals) app-handler h/emit-response))
```

`public/index.php`:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';
\Phel::run(__DIR__ . '/..', 'my-api\\entry');
```

```bash
php -S localhost:8000 -t public
```

## Middleware

```phel
(defn- log-mw [handler req]
  (let [resp (handler req)]
    (println (:method req) (get-in req [:uri :path]) "->" (:status resp))
    resp))

(def app-handler
  (r/handler app-router
    {:middleware [log-mw]
     :not-found  (fn [_] (h/response-from-map {:status 404 :body "nope"}))}))
```

Route-level in data: `{:middleware [auth-mw] :get {:handler ...}}`. Method-level inside the method map.

## Responses

```phel
(h/response-from-string "ok")                                  ; 200 plain
(h/response-from-map {:status 201 :headers {:x "y"} :body s})  ; custom
```

## URL generation

```phel
(def tree [["/users/{id}" {:name :user-show :get {:handler show}}]])
(r/generate app-router :user-show {:id 42})   ; => "/users/42"
```

## Compiled router (prod)

```phel
(def app-router (r/compiled-router [["/ping" {:get {:handler pong}}]]))
```

Routes must be literal at call site. ~3x faster.

## Tests

```phel
(ns tests\handlers-test
  (:require phel\test :refer [deftest is])
  (:require phel\http :as h)
  (:require phel\json :as json)
  (:require my-api\handlers :refer [health greet]))

(deftest health-ok
  (is (= 200 (get (health (h/request-from-map {})) :status))))

(deftest greet-uses-path-param
  (let [req  (h/request-from-map
               {:attributes {:match {:path-params {:name "Alice"}}}})
        resp (greet req)]
    (is (= "Hello, Alice!" (get (json/decode (get resp :body)) :message)))))
```

POST body tests: pass `:parsed-body` map. Production adds JSON-decode middleware.

## Production

```bash
./vendor/bin/phel build
```

Swap `\Phel::run(...)` for `require` of compiled boot file.

## Gotchas

- Handlers return structs, not PHP objects.
- Path params at `[:attributes :match :path-params :<name>]`.
- `*build-mode*` guard in `entry.phel` is mandatory.
- `compiled-router` needs literal routes.

## Next

`src/phel/router.phel`, `src/phel/http.phel`, `tasks/use-php-libs.md`, `docs/framework-integration.md`

# HTTP app

Bundled modules: `phel\http` (req/res structs), `phel\router` (nested routes, middleware, URL gen), `phel\json`. Outbound HTTP: `phel\http-client`.

Runnable: `.agents/examples/todo-app/`.

## Handlers

`src/handlers.phel`:

```phel
(ns my-api\handlers
  (:require phel\http :as h)
  (:require phel\json :as json))

(defn- json-response [status data]
  (h/response-from-map
    {:status  status
     :headers {:content-type "application/json"}
     :body    (json/encode data)}))

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
     ["/greet/{name}" {:name :greet :get {:handler h/greet}}]]))

(def app-handler (r/handler app-router))
```

Data keys per route:
- `:handler` — method-agnostic `(fn [req] resp)`
- `:get` / `:post` / `:put` / `:patch` / `:delete` / `:head` / `:options` — per-method `{:handler :middleware}`
- `:middleware` — vec of `(fn [handler req])` applied at this level
- `:name` — keyword, enables URL generation

Nested children inherit path prefix and deep-merged data.

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
    {:middleware         [log-mw]
     :not-found          (fn [_] (h/response-from-map {:status 404 :body "nope"}))
     :method-not-allowed (fn [_] (h/response-from-map {:status 405 :body "no"}))
     :not-acceptable     (fn [_] (h/response-from-map {:status 406 :body "no"}))
     :default-handler    (fn [_] (h/response-from-map {:status 404}))}))
```

Route-level: `{:middleware [auth-mw] :get {:handler ...}}`. Method-level goes inside the method map.

## Responses

```phel
(h/response-from-string "ok")                                  ; 200 plain
(h/response-from-map {:status 201 :headers {:x "y"} :body s})  ; custom
```

## URL generation

```phel
(:require phel\router :as r :refer [generate match-by-name])

(def app-router
  (r/router [["/users/{id}" {:name :user-show :get {:handler show}}]]))

(generate app-router :user-show {:id 42})   ; => "/users/42"
(match-by-name app-router :user-show)       ; => match struct
```

`generate`, `match-by-name`, `match-by-path` are `Router` interface methods — call directly on the router.

## Compiled router (prod)

```phel
(def app-router (r/compiled-router [["/ping" {:get {:handler pong}}]]))
```

Macro — routes must be literal at call site. ~3x faster matching + URL gen.

## Outbound HTTP

```phel
(:require phel\http-client :as hc)

(hc/get  "https://api.example.com/users/42")
(hc/post "https://api.example.com/users"
         {:json    {:name "Alice"}
          :headers {:authorization (str "Bearer " token)}
          :timeout 10.0})
```

Returns `h/response` struct. Opts: `:headers` `:body` `:json` `:query-params` `:timeout` `:follow-redirects` `:verify-ssl`.

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

POST body: pass `:parsed-body` in the map. Production decodes JSON in middleware.

## Production

```bash
./vendor/bin/phel build
```

Swap `\Phel::run(...)` for `require` of compiled boot file.

## Gotchas

- Handlers return `response` structs via `response-from-map`/`response-from-string`, not raw maps.
- Path params at `[:attributes :match :path-params :<name>]`; route data at `[:attributes :route-data]`.
- `*build-mode*` guard in `entry.phel` is mandatory.
- `compiled-router` needs literal routes (macro expansion).
- Nil response from a matched handler triggers `:not-acceptable` (HTTP 406).
- `:default-handler` is a fallback for any 404/405/406 not covered by a specific override.

## Next

`src/phel/router.phel`, `src/phel/http.phel`, `src/phel/http-client.phel`, `tasks/use-php-libs.md`, `docs/framework-integration.md`

# Mocking in Phel

Replace dependencies with controlled stand-ins for testing. `phel.mock` ships with phel-lang (no separate install); require it from test namespaces only.

```phel
(ns my-app.test.user-test
  (:require phel.test :refer [deftest is])
  (:require phel.mock :refer [mock called-with?]))

(deftest test-fetch-user
  (let [mock-http (mock {:id 1 :name "Alice"})]
    (with-redefs [http-get mock-http]
      (fetch-user 123)
      (is (called-with? mock-http "/users/123")))))
```

## Creating Mocks

**Fixed value**
```phel
(mock {:status 200})
```

**Custom behavior**
```phel
(mock-fn (fn [x] (* x 2)))
```

**Spy (keep original)**
```phel
(spy original-function)
```

**Sequence of values**
```phel
(mock-returning [nil nil {:status 200}])
```

**Throws error**
```phel
(mock-throwing (php/new \Exception "Error"))
```

## Inspecting Mocks

```phel
(calls mock)              ; [[1 2] [3]] - all argument lists
(call-count mock)         ; 2
(called? mock)            ; true  - called at least once
(never-called? mock)      ; false
(called-once? mock)       ; false
(called-times? mock 2)    ; true
(called-with? mock 1 2)   ; true  - exact args
(first-call mock)         ; [1 2]
(last-call mock)          ; [3]
(mock? mock)              ; true
```

Reset one mock's call history without removing it from the registry:
```phel
(reset-mock! mock)
```

## Auto-Reset

`with-mocks` wraps `with-redefs` and resets every mock after the body runs:
```phel
(with-mocks [http-get (mock {:status 200})]
  (fetch-data)
  (is (called-once? http-get)))
;; http-get automatically reset here
```

For wrapped mocks (PHP interop), use `with-mock-wrapper`; it resets the underlying mock even when it is wrapped in an adapter function:
```phel
(with-mock-wrapper [symfony-service mock-http
                    (fn [args] (mock-http (adapt args)))]
  (symfony-service {:key "value"})
  (is (called-once? mock-http)))
;; mock-http automatically reset
```

## Cleanup

Clear the entire registry (useful between test suites in long-running processes):
```phel
(clear-all-mocks!)
```

See `tests/phel/mock.phel` for more examples.

---

📖 **Full guide:** [Testing on phel-lang.org](https://phel-lang.org/documentation/testing/)

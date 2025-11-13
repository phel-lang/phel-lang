# Mocking in Phel

Replace dependencies with controlled stand-ins for testing.

```phel
(ns my-app\test\user-test
  (:require phel\test :refer [deftest is])
  (:require phel\mock :refer [mock called-with?]))

(deftest test-fetch-user
  (let [mock-http (mock {:id 1 :name "Alice"})]
    (binding [http-get mock-http]
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
(calls mock)              # [[1 2] [3]] - all calls
(call-count mock)         # 2
(called? mock)            # true
(called-once? mock)       # false
(called-with? mock 1 2)   # true
(first-call mock)         # [1 2]
(last-call mock)          # [3]
```

## Auto-Reset

```phel
(with-mocks [http-get (mock {:status 200})]
  (fetch-data)
  (is (called-once? http-get)))
# Automatically reset here
```

For wrapped mocks (PHP/Janet interop):
```phel
(with-mock-wrapper [symfony-service mock-http
                    (fn [args] (mock-http (adapt args)))]
  (symfony-service {:key "value"})
  (is (called-once? mock-http)))
# mock-http automatically reset
```

## Cleanup

For long-running processes:
```phel
(clear-all-mocks!)
```

---

See `tests/phel/test/mock.phel` for more examples.

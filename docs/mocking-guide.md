# Mocking in Phel

Test code that calls external services (APIs, databases, etc.) without actually hitting them. Mocking replaces dependencies with controlled, predictable stand-ins.

**Coming from PHP/PHPUnit?** Think lightweight PHPUnit mocks, more functional.
**Coming from Clojure?** Like `with-redefs` with built-in call tracking.
**Coming from Janet?** Similar to `with`, but tracks calls automatically.

## Quick Start

```phel
(ns my-app\test\user-service-test
  (:require phel\test :refer [deftest is])
  (:require phel\mock :refer [mock spy calls called? called-with?]))
```

## Basic Usage

### `mock` - Return Fixed Value

```phel
(deftest test-fetch-user
  (let [mock-http (mock {:id 1 :name "Alice"})]
    (binding [http-get mock-http]
      (is (= {:id 1 :name "Alice"} (http-get "/users/123")))
      (is (called-with? mock-http "/users/123")))))
```

### `mock-fn` - Custom Behavior

```phel
(deftest test-calculate-tax
  (let [mock-calc (mock-fn (fn [amount] (* amount 0.21)))]
    (is (= 21 (mock-calc 100)))
    (is (called-with? mock-calc 100))))
```

### `spy` - Track Calls, Keep Original Behavior

```phel
(deftest test-with-logging
  (let [spy-log (spy log-message)]
    (binding [log-message spy-log]
      (process-payment 100)
      (is (called-with? spy-log "Payment processed")))))
```

### `mock-returning` - Different Values Per Call

```phel
(deftest test-retry-logic
  (let [mock-api (mock-returning [nil nil {:status 200}])]
    # First call returns nil, second nil, third succeeds
    (is (nil? (mock-api)))
    (is (nil? (mock-api)))
    (is (= {:status 200} (mock-api)))
    (is (= 3 (call-count mock-api)))))
```

### `mock-throwing` - Test Error Handling

```phel
(deftest test-error-handling
  (let [mock-api (mock-throwing (php/new \RuntimeException "Unavailable"))]
    (binding [fetch-api mock-api]
      (is (thrown? \RuntimeException (fetch-api "/data"))))))
```

## API Reference

### Creating Mocks
- `(mock value)` - Always returns `value`
- `(mock-fn fn)` - Wraps function, tracks calls
- `(spy fn)` - Like `mock-fn`, clearer intent
- `(mock-returning [v1 v2 ...])` - Returns values sequentially
- `(mock-throwing exception)` - Throws when called

### Inspecting Mocks
- `(calls mock)` - All argument lists `[[1 2] [3]]`
- `(call-count mock)` - Number of calls
- `(called? mock)` - Called at least once?
- `(called-once? mock)` - Called exactly once?
- `(called-times? mock n)` - Called n times?
- `(never-called? mock)` - Never called?
- `(called-with? mock & args)` - Called with exact args?
- `(first-call mock)` / `(last-call mock)` - Get call args
- `(reset-mock! mock)` - Clear call history
- `(clear-all-mocks!)` - Clear registry (long-running processes)
- `(mock? f)` - Is it a mock?

### Auto-Reset with `with-mocks`

```phel
(deftest test-service
  (let [mock-http (mock {:status 200})]
    (with-mocks [http-get mock-http]
      (fetch-data)
      (is (called-once? mock-http)))
    # Call history automatically reset here
    (is (never-called? mock-http))))
```

**Note:** Only works when passing mocks directly. If wrapped in a function, use `reset-mock!` manually.

## Real-World Example

```phel
(defn register-user [email-fn user]
  (email-fn (:email user) "Welcome!" "Thanks for signing up!"))

(deftest test-registration
  (let [mock-email (mock :sent)]
    (binding [send-email mock-email]
      (register-user mock-email {:email "test@example.com"})
      (is (called-once? mock-email))
      (is (called-with? mock-email "test@example.com" "Welcome!" "Thanks for signing up!")))))
```

## Best Practices

**Be Specific**
```phel
(is (called-with? mock "/users/123"))  # Good
(is (called? mock))                     # Less useful
```

**Use Clear Names**
```phel
(let [mock-email-service (mock :sent)])  # Good
(let [m (mock :sent)])                   # Bad
```

**Don't Over-Mock**
If you're mocking 5+ dependencies, consider refactoring your code.

**Reset Between Tests**
```phel
(deftest test-one
  (my-mock)
  (reset-mock! my-mock))  # Clean up
```

## Memory Management

For long-running processes (Laravel Octane, Symfony workers):

```phel
(defn teardown []
  (clear-all-mocks!))  # Call between test suites
```

- `reset-mock!` - Clears history, keeps mock registered
- `clear-all-mocks!` - Removes all mocks from registry

## Common Patterns

```phel
# API Client
(binding [http-get (mock {:data "response"})]
  (test-api-client))

# Database
(binding [db-query (mock [{:id 1 :name "Alice"}])]
  (test-repository))

# Time-Dependent
(binding [current-timestamp (mock 1000000)]
  (test-expiration-logic))

# External/Interop Functions
(binding [external-service (mock :result)]
  (test-integration))
```

## Troubleshooting

**Mock not being called?** Use `binding` to replace the function.

**Unexpected arguments?** `(calls mock)` returns `[[args]]` not `[args]`.

**Behavior not persisting?** `binding` only affects dynamic scope.

## Quick Comparison

| Feature       | Phel         | Clojure            | PHPUnit        |
|---------------|--------------|--------------------|----------------|
| Basic mock    | `(mock val)` | `(constantly val)` | `createMock()` |
| Call tracking | Built-in     | Manual (atom)      | Built-in       |
| Rebinding     | `binding`    | `with-redefs`      | N/A            |
| Auto-reset    | `with-mocks` | Manual             | `tearDown`     |

---

**See also:** `tests/phel/test/mock.phel` for more examples

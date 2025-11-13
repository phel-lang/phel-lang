# Mocking in Phel: A Complete Guide

So you want to test code that talks to APIs, databases, or other external services without actually hitting them? Welcome to the wonderful world of mocking! This guide will show you how Phel's new mocking framework makes testing a breeze.

## Why Mocking?

Imagine you're testing a function that sends emails. You don't want to spam your inbox every time you run tests, right? Or maybe you're calling an external payment API that costs money per request. Mocking lets you replace these dependencies with controlled, predictable stand-ins.

**If you're coming from PHP/PHPUnit:** Think of Phel mocks as a lightweight version of PHPUnit's mock objects, but more functional and without the verbosity.

**If you're coming from Clojure:** Phel's mocking is inspired by `with-redefs` but uses a registry-based approach (since metadata on functions has limitations in the current implementation).

## Getting Started

First, require the mock module in your test:

```phel
(ns my-app\test\user-service-test
  (:require phel\test :refer [deftest is])
  (:require phel\mock :refer [mock mock-fn spy calls called? called-with?]))
```

## Basic Mocking: The `mock` Function

The simplest form of mocking - just return a fixed value:

```phel
(deftest test-fetch-user
  (let [mock-http (mock {:id 1 :name "Alice"})]
    # Call it however you want - it always returns the same thing
    (is (= {:id 1 :name "Alice"} (mock-http)))
    (is (= {:id 1 :name "Alice"} (mock-http "/users/123")))
    (is (= {:id 1 :name "Alice"} (mock-http {:method "GET"})))

    # Check how it was called
    (is (= [[] ["/users/123"] [{:method "GET"}]] (calls mock-http)))))
```

**PHP developers:** This is like PHPUnit's `$mock->method('fetch')->willReturn($value)` but simpler.

**Clojure developers:** Similar to using `(constantly value)` but with call tracking built in.

## Custom Behavior with `mock-fn`

Need your mock to actually do something? Use `mock-fn`:

```phel
(deftest test-calculate-tax
  (let [mock-tax-calc (mock-fn (fn [amount] (* amount 0.21)))]
    (is (= 21 (mock-tax-calc 100)))
    (is (= 42 (mock-tax-calc 200)))

    # Verify it was called with the right values
    (is (called-with? mock-tax-calc 100))
    (is (called-with? mock-tax-calc 200))))
```

## Real-World Example: Testing Email Notifications

Here's a practical example that PHP developers will recognize:

```phel
# The code we want to test
(defn register-user [email-service user-data]
  "Registers a new user and sends welcome email"
  (let [user (save-user! user-data)
        result (email-service
                 (:email user)
                 "Welcome to Our App!"
                 "Thanks for signing up!")]
    (when (not= :sent result)
      (throw (php/new \Exception "Email failed")))
    user))

# The test
(deftest test-user-registration
  (let [mock-email (mock :sent)
        user-data {:email "test@example.com" :name "Test User"}]

    # Use binding to replace the email service temporarily
    (binding [send-email (fn [to subject body] (mock-email to subject body))]
      (register-user nil user-data)

      # Verify email was sent exactly once
      (is (called-once? mock-email) "sends exactly one email")

      # Verify it was sent to the right person
      (is (called-with? mock-email
                       "test@example.com"
                       "Welcome to Our App!"
                       "Thanks for signing up!")))))
```

**PHP equivalent:**
```php
// In PHPUnit
$emailService = $this->createMock(EmailService::class);
$emailService->expects($this->once())
             ->method('send')
             ->with('test@example.com', 'Welcome to Our App!', 'Thanks for signing up!')
             ->willReturn('sent');
```

See how much cleaner Phel is? No need for complex mock builder APIs!

## Spying: Track Calls While Keeping Original Behavior

Sometimes you want to track calls to a function but still let it do its job. That's where `spy` comes in:

```phel
(defn calculate-discount [amount]
  "Real implementation"
  (* amount 0.10))

(deftest test-checkout-with-discount
  (let [spy-calc (spy calculate-discount)]
    (binding [calculate-discount spy-calc]
      (let [discount (calculate-discount 100)]
        # Still calculates correctly
        (is (= 10 discount))

        # But we can verify it was called
        (is (called-once? spy-calc))
        (is (called-with? spy-calc 100))))))
```

**Clojure developers:** This is similar to using a combination of `with-redefs` and an atom to track calls.

## Advanced: `mock-returning` for Changing Behavior

Need your mock to return different values on consecutive calls? Perfect for testing retry logic:

```phel
(defn fetch-with-retry [api-fn url max-retries]
  "Fetches from API with retry logic"
  (try
    (api-fn url)
    (catch \Exception e
      (if (> max-retries 0)
        (fetch-with-retry api-fn url (dec max-retries))
        (throw e)))))

(deftest test-retry-logic
  (let [mock-api (mock-returning [nil nil {:status 200 :data "success"}])]
    (binding [fetch-from-api (fn [url]
                               (let [result (mock-api url)]
                                 (when (nil? result)
                                   (throw (php/new \Exception "API failed")))
                                 result))]
      # First two calls fail, third succeeds
      (is (= {:status 200 :data "success"}
             (fetch-with-retry fetch-from-api "http://api.com" 3)))

      # Verify it retried 3 times
      (is (= 3 (call-count mock-api))))))
```

## Testing Failure Scenarios with `mock-throwing`

Need to test error handling? Make your mock throw an exception:

```phel
(deftest test-api-error-handling
  (let [mock-api (mock-throwing (php/new \RuntimeException "API unavailable"))]
    (binding [fetch-from-api (fn [url] (mock-api url))]
      # Verify your code handles the exception
      (is (thrown? \RuntimeException
                   (fetch-from-api "http://api.com")))

      # Even when throwing, calls are tracked
      (is (called-once? mock-api)))))
```

**PHP developers:** This is cleaner than PHPUnit's `->willThrowException()` because you can use it with `binding` anywhere in your code.

## Complete API Reference

### Creating Mocks

- `(mock value)` - Returns `value` no matter how it's called
- `(mock-fn function)` - Wraps a function and tracks calls
- `(spy function)` - Same as `mock-fn`, just more explicit about intent
- `(mock-returning [val1 val2 ...])` - Returns different values for consecutive calls
- `(mock-throwing exception)` - Throws an exception when called

### Inspecting Mocks

- `(calls mock)` - Returns list of all argument lists: `[[1 2] [3]]`
- `(call-count mock)` - Returns number of times called
- `(called? mock)` - True if called at least once
- `(called-once? mock)` - True if called exactly once
- `(called-times? mock n)` - True if called exactly n times
- `(never-called? mock)` - True if never called
- `(called-with? mock & args)` - True if called with exactly these arguments
- `(first-call mock)` - Returns arguments from first call
- `(last-call mock)` - Returns arguments from last call
- `(reset-mock! mock)` - Clears call history

### Utilities

- `(mock? f)` - True if f is a mock
- `(with-mocks [bindings] & body)` - Like `binding` but automatically resets mocks passed directly in bindings

## Comparing Approaches

### Phel vs Clojure

**Clojure:**
```clojure
(with-redefs [http/get (constantly {:status 200})]
  (test-function))
```

**Phel:**
```phel
(let [mock-http (mock {:status 200})]
  (binding [http/get (fn [url] (mock-http url))]
    (test-function)
    (is (called? mock-http))))  # Extra: call tracking!
```

Phel's approach gives you explicit call tracking, while Clojure's `with-redefs` is slightly more concise but doesn't track calls out of the box.

### Phel vs PHPUnit

**PHPUnit:**
```php
$mock = $this->createMock(HttpClient::class);
$mock->expects($this->once())
     ->method('get')
     ->with('http://api.com')
     ->willReturn(['status' => 200]);
```

**Phel:**
```phel
(let [mock-http (mock {:status 200})]
  (binding [http-get (fn [url] (mock-http url))]
    (test-function)
    (is (called-with? mock-http "http://api.com"))))
```

Phel is more concise and doesn't require configuring expectations upfront - just check after the fact.

## Automatic Mock Reset with `with-mocks`

The `with-mocks` macro automatically resets mock call history when you pass mocks directly in the bindings. This prevents call counts from leaking between tests:

```phel
(deftest test-user-service
  (let [mock-http (mock {:status 200})]
    # First test - mock gets called
    (with-mocks [http-get mock-http]
      (fetch-user 123)
      (is (called-once? mock-http)))

    # After with-mocks, call history is automatically reset!
    (is (never-called? mock-http))

    # Second test - starts with clean slate
    (with-mocks [http-get mock-http]
      (fetch-user 456)
      (is (called-once? mock-http)))))
```

**Important:** Auto-reset only works when you pass mocks **directly** in the bindings. If you wrap the mock in a function, you need to manually reset:

```phel
# This won't auto-reset (mock is wrapped)
(with-mocks [my-fn (fn [& args] (my-mock (transform args)))]
  ...
  (reset-mock! my-mock))  # Manual reset needed

# This will auto-reset (mock passed directly)
(with-mocks [my-fn my-mock]
  ...)  # Automatically reset after this block
```

## Best Practices

### 1. Be Specific About What You're Testing

```phel
# Good - tests exact behavior
(is (called-with? mock-api "/users/123"))

# Also good - tests call count
(is (called-once? mock-api))

# Less useful - just checks it was called somehow
(is (called? mock-api))
```

### 2. Use `spy` When You Mean Spy

```phel
# If you're keeping the original behavior, be explicit
(let [spy-logger (spy log-message)]
  (binding [log-message spy-logger]
    (process-payment 100)
    (is (called-with? spy-logger "Payment processed"))))
```

### 3. Reset Mocks Between Tests

```phel
(def shared-mock (mock :result))

(deftest test-one
  (shared-mock)
  (is (called-once? shared-mock))
  (reset-mock! shared-mock))  # Clean up!

(deftest test-two
  (is (never-called? shared-mock))  # Starts fresh
  (shared-mock))
```

### 4. Don't Over-Mock

```phel
# Bad - mocking too much
(let [mock-a (mock 1)
      mock-b (mock 2)
      mock-c (mock 3)
      mock-d (mock 4)]
  ...)

# Better - maybe your function is doing too much?
# Consider splitting it up!
```

### 5. Name Your Mocks Clearly

```phel
# Clear
(let [mock-email-service (mock :sent)]
  ...)

# Unclear
(let [m (mock :sent)]
  ...)
```

## Common Patterns

### Testing API Clients

```phel
(deftest test-github-client
  (let [mock-http (mock {:username "alice" :repos 42})]
    (binding [http-get (fn [url] (mock-http url))]
      (let [user (fetch-github-user "alice")]
        (is (= "alice" (:username user)))
        (is (called-with? mock-http "https://api.github.com/users/alice"))))))
```

### Testing Database Operations

```phel
(deftest test-user-repository
  (let [mock-db (mock [{:id 1 :name "Alice"}])]
    (binding [db-query (fn [sql] (mock-db sql))]
      (let [users (find-all-users)]
        (is (= 1 (count users)))
        (is (called-with? mock-db "SELECT * FROM users"))))))
```

### Testing Time-Dependent Code

```phel
(deftest test-is-expired
  (let [mock-now (mock 1000000)]
    (binding [current-timestamp mock-now]
      (is (= true (is-expired? {:expires-at 999999})))
      (is (= false (is-expired? {:expires-at 1000001}))))))
```

## Troubleshooting

### "Mock not being called"

Make sure you're using `binding` to replace the function:

```phel
# Won't work - function not replaced
(let [mock-fn (mock :result)]
  (my-function-that-calls-something))

# Works - function replaced with binding
(let [mock-fn (mock :result)]
  (binding [some-function (fn [& args] (apply mock-fn args))]
    (my-function-that-calls-something)))
```

### "Calls showing unexpected arguments"

Remember that `[& args]` captures all arguments as a list:

```phel
(let [my-mock (mock :result)]
  (my-mock 1 2 3)
  (calls my-mock))  # => [[1 2 3]] not [1 2 3]
```

### "Mock behavior not persisting"

`binding` only affects the dynamic scope:

```phel
(binding [my-fn (fn [] :mocked)]
  (my-fn))  # => :mocked

(my-fn)  # => original behavior (binding is gone)
```

## Wrap Up

That's it! You now have everything you need to write well-tested Phel code without hitting external services. The mocking framework is designed to be simple, functional, and get out of your way.

Remember:
- Use `mock` for simple return values
- Use `mock-fn` or `spy` for custom behavior
- Use `mock-returning` for changing behavior over time
- Use `mock-throwing` for testing error paths
- Always verify calls with `called?`, `called-with?`, etc.
- Use `binding` to replace functions in your code

Happy testing! 🎉

## Further Reading

- Check out the tests in `tests/phel/test/mock.phel` for more examples
- Read about Clojure's `with-redefs`: https://clojuredocs.org/clojure.core/with-redefs
- Learn more about functional testing patterns in "Growing Object-Oriented Software, Guided by Tests"

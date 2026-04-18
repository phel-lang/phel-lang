# Common Patterns and Idioms

Practical patterns for writing idiomatic Phel code.

## Table of Contents

- [Working with Nil](#working-with-nil)
- [Collection Transformations](#collection-transformations)
- [Threading Macros](#threading-macros)
- [Error Handling](#error-handling)
- [State Management](#state-management)
- [Recursion and Looping](#recursion-and-looping)
- [Destructuring](#destructuring)
- [Data Validation](#data-validation)
- [Writing Macros](#writing-macros)
- [Build-safe Entry Points](#build-safe-entry-points)

## Working with Nil

### Safe Navigation

```phel
;; Instead of nested ifs
(if user
  (if (get user :profile)
    (if (get (get user :profile) :address)
      (get (get (get user :profile) :address) :city)
      nil)
    nil)
  nil)

;; Use threading with get
(-> user
    (get :profile)
    (get :address)
    (get :city))

;; Or use get-in
(get-in user [:profile :address :city])
```

### Default Values

```phel
;; Provide defaults
(get config :port 8080)                    ; Default to 8080
(or (get config :host) "localhost")        ; Fallback to localhost

;; Multiple fallbacks
(or (get-in user [:settings :theme])
    (get-in defaults [:theme])
    "light")
```

### Nil-Safe Operations

```phel
;; when - executes body only if condition is truthy
(when user
  (println "User:" (get user :name))
  (send-email user))

;; when-let - bind and check in one
(when-let [email (get user :email)]
  (send-notification email))

;; if-let - with else clause
(if-let [role (get user :role)]
  (str "User is a " role)
  "User has no role")

;; some? - true when value is not nil (1-arg form)
(some? user)                              ; => true if user is not nil
(filter some? [1 nil 2 nil 3])            ; => [1 2 3]

;; boolean - coerce any value to true/false
(boolean nil)                             ; => false
(boolean 0)                               ; => true   ; only nil/false are falsy
(boolean "")                              ; => true
```

## Collection Transformations

### Map Operations

```phel
;; Transform all elements
(map inc [1 2 3])                          ; => [2 3 4]
(map str/upper-case ["hello" "world"])     ; => ["HELLO" "WORLD"]
(map #(* % 2) [1 2 3 4])                   ; => [2 4 6 8]

;; Map with index
(map-indexed (fn [i x] [i x]) ["a" "b" "c"])
;; => [[0 "a"] [1 "b"] [2 "c"]]

;; Map over multiple collections
(map + [1 2 3] [10 20 30])                 ; => [11 22 33]
(map str ["a" "b"] [1 2])                  ; => ["a1" "b2"]
```

### Filter Operations

```phel
;; Keep matching elements
(filter even? [1 2 3 4 5 6])               ; => [2 4 6]
(filter #(> % 10) [5 15 8 20 3])           ; => [15 20]

;; Remove matching elements
(remove nil? [1 nil 2 nil 3])              ; => [1 2 3]
(remove empty? ["a" "" "b" "" "c"])        ; => ["a" "b" "c"]

;; Keep non-nil results
(keep (fn [x] (when (even? x) (* x 2)))
      [1 2 3 4 5])                         ; => [4 8]
```

### Reduce Operations

```phel
;; Sum
(reduce + 0 [1 2 3 4 5])                   ; => 15

;; Build a map
(reduce (fn [acc [k v]] (assoc acc k v))
        {}
        [[:a 1] [:b 2] [:c 3]])            ; => {:a 1 :b 2 :c 3}

;; Group by
(reduce (fn [acc x]
          (let [key (if (even? x) :even :odd)]
            (update acc key #(conj (or % []) x))))
        {:even [] :odd []}
        [1 2 3 4 5 6])
;; => {:even [2 4 6] :odd [1 3 5]}

;; Find maximum
(let [coll [3 1 4 1 5 9 2 6]]
  (reduce (fn [max x] (if (> x max) x max))
          (first coll)
          (rest coll)))
;; => 9
```

### Partition and Group

```phel
;; Split into chunks
(partition 2 [1 2 3 4 5 6])                ; => [[1 2] [3 4] [5 6]]
(partition-all 3 [1 2 3 4 5])              ; => [[1 2 3] [4 5]]

;; Group by predicate
(group-by even? [1 2 3 4 5 6])
;; => {true [2 4 6] false [1 3 5]}

(group-by :type [{:type :fruit :name "apple"}
                 {:type :veg :name "carrot"}
                 {:type :fruit :name "banana"}])
;; => {:fruit [{:type :fruit :name "apple"}
;;             {:type :fruit :name "banana"}]
;;     :veg [{:type :veg :name "carrot"}]}
```

## Threading Macros

### Thread-First `->`

Threads value as **first** argument:

```phel
;; Without threading
(get (assoc (dissoc user :password) :active true) :name)

;; With threading
(-> user
    (dissoc :password)
    (assoc :active true)
    (get :name))

;; Practical example
(-> "  Hello World  "
    (str/trim)
    (str/lower-case)
    (str/replace " " "-"))
;; => "hello-world"
```

### Thread-Last `->>`

Threads value as **last** argument:

```phel
;; Process a collection
(->> [1 2 3 4 5 6 7 8 9 10]
     (filter even?)
     (map #(* % 2))
     (reduce +))
;; => 60

;; Data pipeline
(->> users
     (filter #(get % :active))
     (map #(get % :email))
     (filter some?)
     (take 10))
```

### When to Use Which

```phel
;; -> for object/map operations (first arg)
(-> user
    (get :profile)
    (assoc :verified true))

;; ->> for collection operations (last arg)
(->> numbers
     (filter pos?)
     (map square)
     (reduce +))
```

## Error Handling

### Try-Catch

```phel
(defn safe-divide [a b]
  (try
    (/ a b)
    (catch php\DivisionByZeroError e
      (println "Cannot divide by zero")
      nil)))

(defn parse-json [str]
  (try
    (php/json_decode str true)
    (catch php\JsonException e
      {:error (php/-> e (getMessage))})))
```

### Result Types (Either Pattern)

```phel
;; Return maps with :ok or :error
(defn validate-email [email]
  (if (str/contains? email "@")
    {:ok email}
    {:error "Invalid email format"}))

(defn process-user [data]
  (let [result (validate-email (get data :email))]
    (if (get result :ok)
      (create-user data)
      result)))

;; Alternative: use nil for errors
(defn safe-parse-int [s]
  (when (php/is_numeric s)
    (php/intval s)))
```

### Validation Chains

```phel
(defn validate [validations value]
  (reduce (fn [val validator]
            (when val (validator val)))
          value
          validations))

(def email-validations
  [#(when-not (str/contains? % "@") nil)
   #(when (< (php/strlen %) 5) nil)
   #(str/trim %)])

(validate email-validations "  user@example.com  ")
;; => "user@example.com" or nil
```

## State Management

### Atoms (Mutable References)

```phel
;; Create atom
(def counter (atom 0))

;; Read value
(deref counter)                            ; => 0
@counter                                   ; Same (@ is shorthand)

;; Update value
(swap! counter inc)                        ; => 1
(swap! counter #(+ % 10))                  ; => 11
(swap! counter + 5)                        ; => 16

;; Set value directly
(reset! counter 0)                         ; => 0
```

### Managing Application State

```phel
(def app-state
  (atom {:users []
         :current-user nil
         :loading false}))

(defn add-user [user]
  (swap! app-state update :users #(conj % user)))

(defn set-current-user [user]
  (swap! app-state assoc :current-user user))

(defn toggle-loading []
  (swap! app-state update :loading not))

;; Usage
(add-user {:id 1 :name "Alice"})
(set-current-user {:id 1 :name "Alice"})
(toggle-loading)
```

### Vars (Dynamic Bindings)

```phel
(def ^:dynamic *debug* false)

(defn log [msg]
  (when *debug*
    (println "[DEBUG]" msg)))

;; Temporarily override
(binding [*debug* true]
  (log "This will print")
  (do-something))

(log "This won't print")
```

## Recursion and Looping

### Simple Recursion

```phel
(defn factorial [n]
  (if (<= n 1)
    1
    (* n (factorial (dec n)))))

(defn sum-list [coll]
  (if (empty? coll)
    0
    (+ (first coll) (sum-list (rest coll)))))
```

### Tail Recursion with `recur`

```phel
;; Tail-recursive factorial
(defn factorial [n]
  (loop [n n acc 1]
    (if (<= n 1)
      acc
      (recur (dec n) (* acc n)))))

;; Tail-recursive sum
(defn sum-list [coll]
  (loop [items coll total 0]
    (if (empty? items)
      total
      (recur (rest items) (+ total (first items))))))
```

### Iterating with `loop`

```phel
;; Process items with accumulator
(loop [items [1 2 3 4 5]
       evens []
       odds []]
  (if (empty? items)
    {:evens evens :odds odds}
    (let [x (first items)]
      (if (even? x)
        (recur (rest items) (conj evens x) odds)
        (recur (rest items) evens (conj odds x))))))
;; => {:evens [2 4] :odds [1 3 5]}
```

### Early Exit from Loop

```phel
(defn find-first [pred coll]
  (loop [items coll]
    (cond
      (empty? items) nil
      (pred (first items)) (first items)
      (recur (rest items)))))

(find-first #(> % 10) [2 5 8 12 15])      ; => 12
```

## Destructuring

### Vector Destructuring

```phel
;; Basic
(let [[a b c] [1 2 3]]
  (+ a b c))                               ; => 6

;; With rest
(let [[first & rest] [1 2 3 4 5]]
  [first rest])                            ; => [1 [2 3 4 5]]

;; Nested
(let [[a [b c]] [1 [2 3]]]
  (+ a b c))                               ; => 6

;; In function parameters
(defn process-coords [[x y]]
  (+ x y))

(process-coords [10 20])                   ; => 30
```

### Map Destructuring

```phel
;; Basic keys
(let [{:name name :age age} {:name "Alice" :age 30}]
  (str name " is " age))
;; => "Alice is 30"

;; Shorthand (keys same as binding names)
(let [{:keys [name age]} {:name "Alice" :age 30}]
  (str name " is " age))

;; With defaults
(let [{:keys [name age] :or {age 18}} {:name "Bob"}]
  age)                                     ; => 18

;; In function parameters
(defn greet-user [{:keys [name title] :or {title "User"}}]
  (str "Hello, " title " " name))

(greet-user {:name "Alice" :title "Dr."})  ; => "Hello, Dr. Alice"
(greet-user {:name "Bob"})                 ; => "Hello, User Bob"
```

## Data Validation

### Simple Validators

```phel
(defn valid-email? [email]
  (and (php/is_string email)
       (str/contains? email "@")
       (> (php/strlen email) 5)))

(defn valid-age? [age]
  (and (int? age)
       (>= age 0)
       (<= age 150)))

(defn valid-user? [user]
  (and (valid-email? (get user :email))
       (valid-age? (get user :age))
       (php/is_string (get user :name))))
```

### Validation with Errors

```phel
(defn validate-user [user]
  (let [errors (transient [])]
    (when-not (valid-email? (get user :email))
      (conj errors "Invalid email"))
    (when-not (valid-age? (get user :age))
      (conj errors "Invalid age"))
    (when-not (php/is_string (get user :name))
      (conj errors "Name must be a string"))
    (let [err-list (persistent errors)]
      (if (empty? err-list)
        {:ok user}
        {:errors err-list}))))
```

### Spec-like Validation

```phel
(def user-spec
  {:email [php/is_string #(str/contains? % "@")]
   :age [int? #(>= % 0) #(<= % 150)]
   :name [php/is_string #(> (php/strlen %) 0)]})

(defn validate-field [validators value]
  (all? #(% value) validators))

(defn validate-with-spec [spec data]
  (let [errors
        (reduce-kv
          (fn [acc key validators]
            (if (validate-field validators (get data key))
              acc
              (assoc acc key "Validation failed")))
          {}
          spec)]
    (if (empty? errors)
      {:ok data}
      {:errors errors})))

(validate-with-spec user-spec
  {:email "alice@example.com" :age 30 :name "Alice"})
;; => {:ok {...}}
```

## Writing Macros

### Auto-gensym for Hygienic Bindings

Inside a syntax-quote (`` ` ``), a symbol ending in `#` is replaced with a freshly generated, unique symbol. Reuse the same `name#` to refer to the same generated binding:

```phel
(defmacro unless
  "Evaluates body when test is falsy."
  [test & body]
  `(if ~test nil (do ~@body)))

(defmacro time
  "Times the evaluation of expr."
  [expr]
  `(let [start# (php/microtime true)
         ret#   ~expr]
     (println "Elapsed:" (- (php/microtime true) start#) "secs")
     ret#))
```

`start#` and `ret#` will not collide with bindings in user code, even if the caller already has a `start` or `ret` in scope.

### Implicit `&form` and `&env`

Every `defmacro` body has two implicit symbols available, matching Clojure:

- `&form` — the original macro call as written by the user (useful for source-aware error messages)
- `&env` — a map of locals in scope at the call site, keyed by symbol

```phel
;; Inspect lexical scope at the call site
(defmacro has-local? [sym] (contains? &env sym))

(let [a 1]
  (has-local? a))                       ; => true (a is in scope)

;; Use &form to surface the original call in an exception
(defmacro require-positive [x]
  `(when-not (pos? ~x)
     (throw (php/new \InvalidArgumentException
              (str "Expected positive number, got: " ~x
                   " in " (quote ~&form))))))
```

`.cljc` dialect detection works the same way as Clojure ↔ ClojureScript. `(:ns &env)` is always `nil` under Phel (just like Clojure on the JVM), so a portability macro lands on the right branch:

```phel
(defmacro dialect [] (if (:ns &env) "cljs" "phel"))
(dialect) ; => "phel" when compiled by Phel
```

### Extending `is` with Custom Assertions

`phel\test/assert-expr` is an open multimethod, so you can teach the `is` macro how to expand new assertion forms. A `defmethod` takes the original `form` and the user-supplied `message`, and returns the code that the outer `is` should run instead:

```phel
(ns my-app\test\helpers
  (:require phel\test :refer [deftest is]))

;; Approximate equality for floats — expand to a plain `is` over a tolerance check.
(defmethod phel\test/assert-expr 'approx= [form message]
  (let [a (second form)
        b (second (next form))
        epsilon 0.001]
    `(is (< (php/abs (- ~a ~b)) ~epsilon) ~message)))

(deftest pi-approximation
  (is (approx= 3.14159 (calc-pi)) "calc-pi should land near pi"))
```

When the dispatch symbol has no registered method (e.g. `(is (= 1 1))`), the multimethod's `:default` arm handles binary equality and predicate forms exactly as before, so existing tests are unaffected.

> Cross-namespace registration must use the fully-qualified `phel\test/assert-expr` so the methods table is resolved in `phel\test` rather than the local namespace.

## Tips for Writing Idiomatic Phel

### Prefer Higher-Order Functions

```phel
;; Instead of loop
(loop [coll [1 2 3 4] result []]
  (if (empty? coll)
    result
    (recur (rest coll) (conj result (* 2 (first coll))))))

;; Use map
(map #(* % 2) [1 2 3 4])
```

### Use Threading for Readability

```phel
;; Hard to read
(reduce + (map #(* % 2) (filter even? numbers)))

;; Clear pipeline
(->> numbers
     (filter even?)
     (map #(* % 2))
     (reduce +))
```

### Keep Functions Small

```phel
;; Instead of one big function
(defn process-order [order]
  (-> order
      validate-order
      calculate-total
      apply-discounts
      generate-invoice
      send-confirmation))
```

### Use Descriptive Names

```phel
;; Good
(defn find-active-users [users]
  (filter :active users))

;; Better
(defn active-users [users]
  (filter :active users))

;; Best - with docstring
(defn active-users
  "Returns only active users from the collection."
  [users]
  (filter :active users))
```

## Build-safe Entry Points

`phel build` evaluates every top-level form at compile time so macros, `def`, `defn`, and `ns` register correctly. Top-level **side effects** (starting a game loop, reading `stdin`, opening sockets, sleeping) also run, which can block the build indefinitely.

Guard imperative entry calls with `*build-mode*`:

```phel
(ns app\main)

(defn play []
  (loop [state (initial-state)]
    ;; ... read stdin, render, recur
    ))

;; Safe: only runs when actually executing, not during `phel build`.
(when-not *build-mode*
  (play))
```

`*build-mode*` is set to `true` while the compiler evaluates your file during `phel build`, and `false` during `phel run` or when the compiled artifact is loaded at runtime. The same idea applies to any stdin/network/sleep call at top level:

```phel
;; Bad: blocks `phel build` forever on fgets.
(def line (php/fgets (php/fopen "php://stdin" "r")))

;; Good: reads at run time only.
(def stdin (php/fopen "php://stdin" "r"))

(defn read-line []
  (php/fgets stdin))

(when-not *build-mode*
  (println (read-line)))
```

`defn`, `def` of pure values, `ns`, and `(:require ...)` forms are always safe at top level. Only imperative work needs the guard.

> Note: `phel build` suppresses stdout produced by compiled code during compilation, so stray `println` calls no longer leak to the terminal. Execution still happens though, so anything that **blocks** (stdin reads, `sleep`, sockets, infinite loops) still needs `(when-not *build-mode* ...)`.

## See Also

- [PHP Interop](php-interop.md) - Working with PHP code
- [Quick Start](quickstart.md) - Get started quickly
- [Reader Shortcuts](reader-shortcuts.md) - Syntax reference
- [Lazy Sequences](lazy-sequences.md) - Performance patterns

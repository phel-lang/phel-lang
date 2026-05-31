# Common Patterns and Idioms

## Working with Nil

### Safe Navigation

```phel
;; Nested ifs
(if user
  (if (get user :profile)
    (if (get (get user :profile) :address)
      (get (get (get user :profile) :address) :city)
      nil)
    nil)
  nil)

;; Threading with get
(-> user
    (get :profile)
    (get :address)
    (get :city))

;; Or get-in
(get-in user [:profile :address :city])
```

### Default Values

```phel
(get config :port 8080)                    ; Default 8080
(or (get config :host) "localhost")        ; Fallback

;; Multiple fallbacks
(or (get-in user [:settings :theme])
    (get-in defaults [:theme])
    "light")
```

### Nil-Safe Operations

```phel
;; when: body runs only on truthy
(when user
  (println "User:" (get user :name))
  (send-email user))

;; when-let: bind + check
(when-let [email (get user :email)]
  (send-notification email))

;; if-let: with else
(if-let [role (get user :role)]
  (str "User is a " role)
  "User has no role")

;; some?: not nil
(some? user)                              ; => true if not nil
(filter some? [1 nil 2 nil 3])            ; => [1 2 3]

;; boolean: coerce
(boolean nil)                             ; => false
(boolean 0)                               ; => true   (only nil/false falsy)
(boolean "")                              ; => true
```

## Collection Transformations

### Map Operations

```phel
(map inc [1 2 3])                          ; => [2 3 4]
(map str/upper-case ["hello" "world"])     ; => ["HELLO" "WORLD"]
(map #(* % 2) [1 2 3 4])                   ; => [2 4 6 8]

;; With index
(map-indexed (fn [i x] [i x]) ["a" "b" "c"])
;; => [[0 "a"] [1 "b"] [2 "c"]]

;; Multiple collections
(map + [1 2 3] [10 20 30])                 ; => [11 22 33]
(map str ["a" "b"] [1 2])                  ; => ["a1" "b2"]
```

### Filter Operations

```phel
;; Keep matching
(filter even? [1 2 3 4 5 6])               ; => [2 4 6]
(filter #(> % 10) [5 15 8 20 3])           ; => [15 20]

;; Remove matching
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

;; Maximum
(let [coll [3 1 4 1 5 9 2 6]]
  (reduce (fn [max x] (if (> x max) x max))
          (first coll)
          (rest coll)))
;; => 9
```

### Partition and Group

```phel
;; Chunks
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
;; Without
(get (assoc (dissoc user :password) :active true) :name)

;; With
(-> user
    (dissoc :password)
    (assoc :active true)
    (get :name))

(-> "  Hello World  "
    (str/trim)
    (str/lower-case)
    (str/replace " " "-"))
;; => "hello-world"
```

### Thread-Last `->>`

Threads value as **last** argument:

```phel
(->> [1 2 3 4 5 6 7 8 9 10]
     (filter even?)
     (map #(* % 2))
     (reduce +))
;; => 60

(->> users
     (filter #(get % :active))
     (map #(get % :email))
     (filter some?)
     (take 10))
```

### When to Use Which

```phel
;; -> for object/map ops (first arg)
(-> user
    (get :profile)
    (assoc :verified true))

;; ->> for collection ops (last arg)
(->> numbers
     (filter pos?)
     (map square)
     (reduce +))
```

## Pattern Matching

`phel.match` destructures by shape and binds symbols in one step. Use when `cond`/`case` would re-query the same value from multiple angles.
```phel
(ns app.commands
  (:require phel.match :refer [match]))

(defn handle [event]
  (match [event]
    [{:type :add :value v}]            (str "add " v)
    [{:type :remove :id (_ :guard pos?)}] "remove-valid"
    [[:cmd (:or :up :down) n]]         (str "move " n)
    [[head & rest]]                    (str "list of " (count rest) " after " head)
    :else                              :unknown))
```

Pattern elements:

| Pattern | Matches |
|---------|---------|
| `1`, `"s"`, `:k`, `nil`, `true` | Equality with the literal |
| `_` | Anything (no binding) |
| `x` (bare symbol) | Anything, binds to `x` |
| `[a b c]` | Vector or list of length 3 |
| `[a & more]` | Vector or list, rest captured |
| `{:k v}` | Map containing key `:k`, binds value to `v` |
| `(x :guard pred)` | Value where `(pred x)` is truthy |
| `(pat :as x)` | Match `pat` and also bind whole value to `x` |
| `(:or a b c)` | Any of the alternatives |

The outer `[event]` vector matches several values at once:

```phel
(match [http-status role]
  [200 :admin]       :dashboard
  [200 _]            :ok
  [401 _]            :login
  [(_ :guard #(>= % 500)) _] :error
  :else              :unknown)
```

Without `:else`, `match` throws `RuntimeException` when nothing fits. Add `:else` when "none of these" is valid.

## Error Handling

### Try-Catch

```phel
(defn safe-divide [a b]
  (try
    (/ a b)
    (catch \DivisionByZeroError e
      (println "Cannot divide by zero")
      nil)))

(defn parse-json [str]
  (try
    (php/json_decode str true)
    (catch \JsonException e
      {:error (php/-> e (getMessage))})))
```

### Result Types (Either Pattern)

```phel
;; Maps with :ok or :error
(defn validate-email [email]
  (if (str/contains? email "@")
    {:ok email}
    {:error "Invalid email format"}))

(defn process-user [data]
  (let [result (validate-email (get data :email))]
    (if (get result :ok)
      (create-user data)
      result)))

;; Or nil for errors
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
(def counter (atom 0))

;; Read
(deref counter)                            ; => 0
@counter                                   ; shorthand

;; Update
(swap! counter inc)                        ; => 1
(swap! counter #(+ % 10))                  ; => 11
(swap! counter + 5)                        ; => 16

;; Set
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

;; Override temporarily
(binding [*debug* true]
  (log "This will print")
  (do-something))

(log "This won't print")
```

## Recursion and Looping

### Simple Recursion

```phel
;; Note: `*'` always returns BigInt for integer results.
(defn factorial [^int n]
  (if (<= n 1)
    1
    (*' n (factorial (dec n)))))

(defn sum-list [coll]
  (if (empty? coll)
    0
    (+ (first coll) (sum-list (rest coll)))))
```

### Tail Recursion with `recur`

```phel
(defn factorial [^int n]
  (loop [n n acc 1]
    (if (<= n 1)
      acc
      (recur (dec n) (*' acc n)))))

(defn sum-list [coll]
  (loop [items coll total 0]
    (if (empty? items)
      total
      (recur (rest items) (+ total (first items))))))
```

### Iterating with `loop`

```phel
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
(let [[a b c] [1 2 3]]
  (+ a b c))                               ; => 6

;; Rest
(let [[first & rest] [1 2 3 4 5]]
  [first rest])                            ; => [1 [2 3 4 5]]

;; Nested
(let [[a [b c]] [1 [2 3]]]
  (+ a b c))                               ; => 6

;; Function params
(defn process-coords [[x y]]
  (+ x y))

(process-coords [10 20])                   ; => 30
```

### Map Destructuring

```phel
(let [{:name name :age age} {:name "Alice" :age 30}]
  (str name " is " age))
;; => "Alice is 30"

;; :keys shorthand
(let [{:keys [name age]} {:name "Alice" :age 30}]
  (str name " is " age))

;; :or defaults
(let [{:keys [name age] :or {age 18}} {:name "Bob"}]
  age)                                     ; => 18

;; Function params
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
      (conj! errors "Invalid email"))
    (when-not (valid-age? (get user :age))
      (conj! errors "Invalid age"))
    (when-not (php/is_string (get user :name))
      (conj! errors "Name must be a string"))
    (let [err-list (persistent! errors)]
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
        (reduce
          (fn [acc key]
            (if (validate-field (get spec key) (get data key))
              acc
              (assoc acc key "Validation failed")))
          {}
          (keys spec))]
    (if (empty? errors)
      {:ok data}
      {:errors errors})))

(validate-with-spec user-spec
  {:email "alice@example.com" :age 30 :name "Alice"})
;; => {:ok {...}}
```

## Writing Macros

### Auto-gensym for Hygienic Bindings

Inside syntax-quote (`` ` ``), a symbol ending in `#` expands to a fresh unique symbol. Reuse `name#` to refer to the same generated binding:

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

`start#` and `ret#` won't collide with caller bindings.

### Implicit `&form` and `&env`

Every `defmacro` body has two implicit symbols:

- `&form`: the original macro call (for source-aware error messages)
- `&env`: a map of in-scope locals, keyed by symbol

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

`(:ns &env)` is always `nil` in Phel, so portability macros land on the right branch:

```phel
(defmacro dialect [] (if (:ns &env) "cljs" "phel"))
(dialect) ; => "phel" when compiled by Phel
```

### Extending `is` with Custom Assertions

`phel.test/assert-expr` is an open multimethod. Teach `is` to expand new assertion forms via `defmethod`. The method takes the user-supplied `message` and original `form`, matching `clojure.test/assert-expr`, and returns the code `is` should run:

```phel
(ns my-app.test.helpers
  (:require phel.test :refer [deftest is]))

;; Approximate equality for floats: expand to `is` over a tolerance check.
(defmethod phel.test/assert-expr 'approx= [message form]
  (let [a (second form)
        b (second (next form))
        epsilon 0.001]
    `(is (< (php/abs (- ~a ~b)) ~epsilon) ~message)))

(deftest pi-approximation
  (is (approx= 3.14159 (calc-pi)) "calc-pi should land near pi"))
```

When the dispatch symbol has no registered method (e.g. `(is (= 1 1))`), the `:default` arm handles binary equality and predicate forms.

> Cross-namespace registration must use fully-qualified `phel.test/assert-expr` so the methods table resolves in `phel.test`, not the local namespace.

## Build-safe Entry Points

`phel build` evaluates every top-level form at compile time so macros, `def`, `defn`, and `ns` register. Top-level **side effects** (game loops, `stdin` reads, sockets, sleeps) also run and can block the build indefinitely.

Guard imperative entry calls with `*build-mode*`:

```phel
(ns app.main)

(defn play []
  (loop [state (initial-state)]
    ;; ... read stdin, render, recur
    ))

;; Safe: only runs when actually executing, not during `phel build`.
(when-not *build-mode*
  (play))
```

`*build-mode*` is `true` while the compiler evaluates your file during `phel build`, `false` during `phel run` or runtime artifact loading. Same applies to any top-level stdin/network/sleep call:

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

`defn`, `def` of pure values, `ns`, and `(:require ...)` are always safe at top level. Only imperative work needs the guard.

> `phel build` suppresses stdout from compiled code during compilation, so stray `println` calls don't leak. Execution still happens, so anything that **blocks** (stdin reads, `sleep`, sockets, infinite loops) needs `(when-not *build-mode* ...)`.

## See Also

- [PHP Interop](php-interop.md)
- [Quick Start](quickstart.md)
- [Reader Shortcuts](reader-shortcuts.md)
- [Lazy Sequences](lazy-sequences.md)

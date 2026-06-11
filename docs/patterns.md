# Common Patterns and Idioms

## Working with Nil

```phel
;; Safe navigation: thread get, or get-in
(-> user (get :profile) (get :address) (get :city))
(get-in user [:profile :address :city])

;; Defaults and fallbacks
(get config :port 8080)                       ; default 8080
(or (get-in user [:settings :theme])
    (get-in defaults [:theme])
    "light")

;; Nil-safe binding/control
(when user (println "User:" (get user :name)))
(when-let [email (get user :email)] (send-notification email))
(if-let [role (get user :role)] (str "User is a " role) "User has no role")

;; some?: not nil
(filter some? [1 nil 2 nil 3])                ; => [1 2 3]

;; boolean: only nil/false are falsy
(boolean nil)                                 ; => false
(boolean 0)                                   ; => true
(boolean "")                                  ; => true
```

## Collection Transformations

```phel
;; map
(map inc [1 2 3])                             ; => [2 3 4]
(map #(* % 2) [1 2 3 4])                      ; => [2 4 6 8]
(map-indexed (fn [i x] [i x]) ["a" "b"])      ; => [[0 "a"] [1 "b"]]
(map + [1 2 3] [10 20 30])                    ; => [11 22 33] (multiple colls)

;; filter / remove / keep
(filter even? [1 2 3 4 5 6])                  ; => [2 4 6]
(remove nil? [1 nil 2 nil 3])                 ; => [1 2 3]
(keep (fn [x] (when (even? x) (* x 2)))
      [1 2 3 4 5])                            ; => [4 8] (drops nil results)

;; partition / group-by
(partition 2 [1 2 3 4 5 6])                   ; => [[1 2] [3 4] [5 6]]
(partition-all 3 [1 2 3 4 5])                 ; => [[1 2 3] [4 5]]
(group-by even? [1 2 3 4 5 6])                ; => {true [2 4 6] false [1 3 5]}
(group-by :type [{:type :fruit :name "apple"}
                 {:type :veg :name "carrot"}])
;; => {:fruit [...] :veg [...]}
```

### Reduce

```phel
(reduce + 0 [1 2 3 4 5])                      ; => 15

;; Build a map
(reduce (fn [acc [k v]] (assoc acc k v))
        {} [[:a 1] [:b 2] [:c 3]])            ; => {:a 1 :b 2 :c 3}

;; Group into buckets
(reduce (fn [acc x]
          (update acc (if (even? x) :even :odd) #(conj (or % []) x)))
        {:even [] :odd []} [1 2 3 4 5 6])
;; => {:even [2 4 6] :odd [1 3 5]}

;; Max
(let [coll [3 1 4 1 5 9 2 6]]
  (reduce (fn [m x] (if (> x m) x m)) (first coll) (rest coll)))  ; => 9
```

## Threading Macros

`->` threads the value as the **first** argument; `->>` as the **last**.

```phel
;; -> for map/object ops (first arg)
(-> user
    (dissoc :password)
    (assoc :active true)
    (get :name))

(-> "  Hello World  "
    (str/trim)
    (str/lower-case)
    (str/replace " " "-"))                    ; => "hello-world"

;; ->> for collection ops (last arg)
(->> [1 2 3 4 5 6 7 8 9 10]
     (filter even?)
     (map #(* % 2))
     (reduce +))                              ; => 60
```

## Pattern Matching

`phel.match` destructures by shape and binds symbols in one step. Use it where `cond`/`case` would re-query the same value from multiple angles.

```phel
(ns app.commands
  (:require phel.match :refer [match]))

(defn handle [event]
  (match [event]
    [{:type :add :value v}]               (str "add " v)
    [{:type :remove :id (_ :guard pos?)}] "remove-valid"
    [[:cmd (:or :up :down) n]]            (str "move " n)
    [[head & rest]]                       (str "list of " (count rest) " after " head)
    :else                                 :unknown))
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

The outer `[...]` vector matches several values at once:

```phel
(match [http-status role]
  [200 :admin]              :dashboard
  [200 _]                   :ok
  [401 _]                   :login
  [(_ :guard #(>= % 500)) _] :error
  :else                     :unknown)
```

Without `:else`, `match` throws `RuntimeException` when nothing fits. Add `:else` when "none of these" is valid.

## Error Handling

```phel
;; try/catch
(defn safe-divide [a b]
  (try
    (/ a b)
    (catch \DivisionByZeroError e nil)))

(defn parse-json [s]
  (try
    (php/json_decode s true)
    (catch \JsonException e
      {:error (php/-> e (getMessage))})))

;; Result maps ({:ok ...} / {:error ...})
(defn validate-email [email]
  (if (str/contains? email "@")
    {:ok email}
    {:error "Invalid email format"}))

;; Or nil for errors
(defn safe-parse-int [s]
  (when (php/is_numeric s)
    (php/intval s)))

;; Validation chain: stop at first nil
(defn validate [validations value]
  (reduce (fn [val validator] (when val (validator val)))
          value validations))

(def email-validations
  [#(when-not (str/contains? % "@") nil)
   #(when (< (php/strlen %) 5) nil)
   #(str/trim %)])

(validate email-validations "  user@example.com  ")
;; => "user@example.com" or nil
```

## State Management

### Atoms (mutable references)

```phel
(def counter (atom 0))

(deref counter)            ; => 0
@counter                   ; shorthand for (deref counter)

(swap! counter inc)        ; => 1
(swap! counter + 5)        ; => 6  (extra args passed to fn)
(reset! counter 0)         ; => 0
```

### Application state in one atom

```phel
(def app-state
  (atom {:users [] :current-user nil :loading false}))

(defn add-user [user]
  (swap! app-state update :users #(conj % user)))

(defn set-current-user [user]
  (swap! app-state assoc :current-user user))

(defn toggle-loading []
  (swap! app-state update :loading not))
```

### Vars (dynamic bindings)

```phel
(def ^:dynamic *debug* false)

(defn log [msg]
  (when *debug* (println "[DEBUG]" msg)))

;; Override temporarily
(binding [*debug* true]
  (log "This will print"))

(log "This won't print")
```

## Recursion and Looping

```phel
;; Simple recursion. Note: `*'` always returns BigInt for integer results.
(defn factorial [^int n]
  (if (<= n 1) 1 (*' n (factorial (dec n)))))

;; Tail recursion with recur (no stack growth)
(defn factorial [^int n]
  (loop [n n acc 1]
    (if (<= n 1) acc (recur (dec n) (*' acc n)))))

;; loop accumulating multiple values
(loop [items [1 2 3 4 5] evens [] odds []]
  (if (empty? items)
    {:evens evens :odds odds}
    (let [x (first items)]
      (if (even? x)
        (recur (rest items) (conj evens x) odds)
        (recur (rest items) evens (conj odds x))))))
;; => {:evens [2 4] :odds [1 3 5]}

;; Early exit
(defn find-first [pred coll]
  (loop [items coll]
    (cond
      (empty? items)       nil
      (pred (first items)) (first items)
      (recur (rest items)))))

(find-first #(> % 10) [2 5 8 12 15])          ; => 12
```

## Destructuring

```phel
;; Vector
(let [[a b c] [1 2 3]] (+ a b c))             ; => 6
(let [[first & rest] [1 2 3 4 5]] [first rest]) ; => [1 [2 3 4 5]]
(let [[a [b c]] [1 [2 3]]] (+ a b c))         ; => 6 (nested)

(defn process-coords [[x y]] (+ x y))
(process-coords [10 20])                       ; => 30

;; Map (long form: {:key binding-symbol})
(let [{:name name :age age} {:name "Alice" :age 30}]
  (str name " is " age))                       ; => "Alice is 30"

;; :keys shorthand for same-named bindings
(let [{:keys [name age]} {:name "Alice" :age 30}]
  (str name " is " age))                       ; => "Alice is 30"

;; :or defaults
(let [{:keys [name age] :or {age 18}} {:name "Bob"}] age)  ; => 18

(defn greet-user [{:keys [name title] :or {title "User"}}]
  (str "Hello, " title " " name))
(greet-user {:name "Alice" :title "Dr."})     ; => "Hello, Dr. Alice"
(greet-user {:name "Bob"})                     ; => "Hello, User Bob"
```

## Data Validation

```phel
;; Simple predicate validators
(defn valid-email? [email]
  (and (php/is_string email)
       (str/contains? email "@")
       (> (php/strlen email) 5)))

(defn valid-age? [age]
  (and (int? age) (>= age 0) (<= age 150)))

;; Collect errors with a transient (see Transient Collections in data-structures-guide.md)
(defn validate-user [user]
  (let [errors (transient [])]
    (when-not (valid-email? (get user :email)) (conj! errors "Invalid email"))
    (when-not (valid-age? (get user :age))     (conj! errors "Invalid age"))
    (when-not (php/is_string (get user :name)) (conj! errors "Name must be a string"))
    (let [err-list (persistent! errors)]
      (if (empty? err-list) {:ok user} {:errors err-list}))))

;; Spec-like: map of field -> list of predicates
(def user-spec
  {:email [php/is_string #(str/contains? % "@")]
   :age   [int? #(>= % 0) #(<= % 150)]
   :name  [php/is_string #(> (php/strlen %) 0)]})

(defn validate-field [validators value]
  (all? #(% value) validators))

(defn validate-with-spec [spec data]
  (let [errors (reduce (fn [acc key]
                         (if (validate-field (get spec key) (get data key))
                           acc
                           (assoc acc key "Validation failed")))
                       {} (keys spec))]
    (if (empty? errors) {:ok data} {:errors errors})))

(validate-with-spec user-spec
  {:email "alice@example.com" :age 30 :name "Alice"})   ; => {:ok {...}}
```

## Writing Macros

### Auto-gensym for hygienic bindings

Inside syntax-quote (`` ` ``), a symbol ending in `#` expands to a fresh unique symbol. Reuse `name#` to refer to the same generated binding, so it can't collide with caller bindings:

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

### Implicit `&form` and `&env`

Every `defmacro` body has two implicit symbols:

- `&form`: the original macro call (for source-aware error messages)
- `&env`: a map of in-scope locals, keyed by symbol

```phel
;; Inspect lexical scope at the call site
(defmacro has-local? [sym] (contains? &env sym))
(let [a 1] (has-local? a))                    ; => true

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
(dialect)                                      ; => "phel" when compiled by Phel
```

### Extending `is` with custom assertions

`phel.test/assert-expr` is an open multimethod. Teach `is` new assertion forms via `defmethod`. The method takes the user-supplied `message` and original `form` (matching `clojure.test/assert-expr`) and returns the code `is` should run:

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

`phel build` evaluates every top-level form at compile time so macros, `def`, `defn`, and `ns` register. Top-level **side effects** (game loops, `stdin` reads, sockets, sleeps) also run and can block the build indefinitely. Guard imperative entry points with `*build-mode*`:

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

`*build-mode*` is `true` while the compiler evaluates your file during `phel build`, `false` during `phel run` or runtime artifact loading. The same applies to any top-level stdin/network/sleep call:

```phel
;; Bad: blocks `phel build` forever on fgets.
(def line (php/fgets (php/fopen "php://stdin" "r")))

;; Good: open at top level, read only at run time.
(def stdin (php/fopen "php://stdin" "r"))
(defn read-line [] (php/fgets stdin))
(when-not *build-mode* (println (read-line)))
```

`defn`, `def` of pure values, `ns`, and `(:require ...)` are always safe at top level. Only imperative work needs the guard.

> `phel build` suppresses stdout from compiled code during compilation, so stray `println` calls don't leak. Execution still happens, so anything that **blocks** (stdin reads, `sleep`, sockets, infinite loops) needs `(when-not *build-mode* ...)`.

## See Also

- [PHP Interop](php-interop.md)
- [Quick Start](quickstart.md)
- [Reader Shortcuts](reader-shortcuts.md)
- [Lazy Sequences](lazy-sequences.md)

---

📖 **Full guide:** [Cheat Sheet on phel-lang.org](https://phel-lang.org/documentation/reference/cheat-sheet/)

# Quick syntax reference

One-screen cheatsheet. For exhaustive rules see [`RULES.md`](RULES.md); for typing details see [`tasks/typed-defn.md`](tasks/typed-defn.md).

```phel
;; Define + call (^int :tag emits PHP int return-type and unlocks JIT-friendly call shape)
(defn ^string greet [^string name] (str "Hello, " name "!"))
(greet "World")

;; Multi-arity, name-tag propagates to every arity unless overridden
(defn ^int fib
  ([^int n] (fib n 0 1))
  ([^int n ^int a ^int b]
   (if (zero? n) a (recur (dec n) b (+ a b)))))

;; Memoize + type tag together
(defn ^{:memoize-lru 256 :tag "int"} slow-fib
  [^int n]
  (if (< n 2) n (+ (slow-fib (- n 1)) (slow-fib (- n 2)))))

;; Async defn -> Amp\Future
(defn ^:async fetch [^string url]
  (await (http-get url)))

;; Records
(defrecord Todo [id text done])
(->Todo 1 "Buy milk" false)            ; positional constructor
(map->Todo {:id 1 :text "X" :done false}) ; map constructor

;; Protocols
(defprotocol Showable (show [this]))
(extend-type Todo Showable (show [t] (str "#" (get t :id) " " (get t :text))))

;; Multimethods
(defmulti handle-cmd (fn [cmd & _] cmd))
(defmethod handle-cmd "add" [_ text] (add-item text))
(defmethod handle-cmd :default [cmd & _] (println "Unknown:" cmd))

;; Transducers
(into [] (comp (filter odd?) (map inc)) [1 2 3 4 5])  ; => [2 4 6]
(transduce (map :score) + 0 items)

;; Regex
(re-find #"\d+" "abc123")  ; => "123"

;; JSON
(:require phel.json :as json)
(json/encode {:a 1})  ; => "{\"a\":1}"
(json/decode "{\"a\":1}")  ; => {:a 1}

;; File I/O
(slurp "data.txt")
(spit "out.txt" "content")

;; PHP interop
(php/new \DateTime "now")
(php/-> obj (method args))
(php/:: Class (staticMethod))
```

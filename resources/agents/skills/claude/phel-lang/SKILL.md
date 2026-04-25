---
name: phel-lang
description: Building applications WITH Phel (Lisp that compiles to PHP). Triggers on .phel files, phel-config.php, phel CLI commands (init, run, repl, test, build), or requests to build a Phel app. Skip when working on the Phel compiler internals (use compiler-guide or phel-patterns instead).
---

# Phel

Lisp dialect that compiles to PHP. PHP interop via `php/` prefix.

## Load order

1. `.agents/RULES.md` — hard rules, new features reference, and CLI cheatsheet
2. `.agents/tasks/common-gotchas.md` — read BEFORE writing code to avoid common pitfalls
3. `.agents/index.md` — task map
4. `.agents/tasks/<intent>.md` — recipe for the current task
5. `src/phel/` and `docs/` only when a recipe points there

## Before you start coding

- Read `.agents/RULES.md` § Gotchas — saves 5-10 min of debugging
- Use `*argv*` for CLI args, NOT `php/$argv`
- Use `doseq` for side effects, `for` for building sequences
- String module is `phel\string` (not `phel\str`)
- Use `defrecord`, `defprotocol`, `defmulti` for structured code — they are stable since v0.30-0.32

## Working examples

`.agents/examples/{todo-app, http-json-api, cli-wordcount}/` — copy, adapt, run.

## Quick syntax reference

```phel
;; Define + call
(defn greet [name] (str "Hello, " name "!"))
(greet "World")

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
(:require phel\json :as json)
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

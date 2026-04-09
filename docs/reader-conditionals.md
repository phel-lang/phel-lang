# Reader Conditionals in Phel

## What are reader conditionals?

Reader conditionals allow platform-specific code in shared source files. They are evaluated at read time (during parsing), before compilation. The Phel compiler selects the `:phel` branch, ignores branches for other platforms (`:clj`, `:cljs`, etc.), and optionally falls back to `:default`.

This enables `.cljc` files — source files that can be shared between Phel, Clojure, and other Lisp dialects that support reader conditionals.

## Basic usage: `#?()`

A reader conditional selects one form based on the platform:

```phel
#?(:phel  (php/time)
   :clj   (System/currentTimeMillis)
   :cljs  (js/Date.now))
; => In Phel: evaluates (php/time)
; => In Clojure: evaluates (System/currentTimeMillis)
; => In ClojureScript: evaluates (js/Date.now)
```

### Platform keys

| Key | Platform | Matched by Phel? |
|-----|----------|-------------------|
| `:phel` | Phel | Yes |
| `:default` | Any platform (fallback) | Yes (if no `:phel`) |
| `:clj` | Clojure (JVM) | No |
| `:cljs` | ClojureScript | No |
| Any other | Ignored | No |

### Priority rules

1. `:phel` is always selected if present, regardless of position
2. `:default` is used as a fallback when no `:phel` branch exists
3. If neither `:phel` nor `:default` is present, the entire form is dropped

```phel
;; :phel takes priority over :default
#?(:default 0 :phel 42)
; => 42

;; :default is the fallback
#?(:clj 99 :default 0)
; => 0

;; No matching branch — form is dropped entirely
#?(:clj 99 :cljs 88)
; => (nothing — treated as whitespace)
```

## Splicing: `#?@()`

Reader conditional splicing inserts multiple elements from a collection into the surrounding form:

```phel
[1 #?@(:phel [2 3]) 4]
; => [1 2 3 4]

(php/array 0 #?@(:phel [1 2 3]) 4)
; => array(0, 1, 2, 3, 4)
```

The matched branch **must be a sequential collection** (vector or list). Its elements are spliced into the parent — the collection wrapper is removed.

### Splicing with fallback

```phel
[1 #?@(:clj [8 9] :default [2 3]) 4]
; => [1 2 3 4]
```

### No match splices nothing

```phel
[1 #?@(:clj [8 9]) 4]
; => [1 4]
```

### Top-level restriction

`#?@()` is only valid inside a collection (list, vector, map, set). Using it at the top level is an error:

```phel
;; ERROR: Reader conditional splicing #?@() is not allowed at the top level
#?@(:phel [1 2])
```

## Use cases

### Cross-platform source files (`.cljc`)

Phel automatically discovers and compiles `.cljc` files alongside `.phel` files. This lets you share code between Phel and Clojure:

```phel
;; src/shared/utils.cljc
(ns shared.utils)

(defn now []
  #?(:phel  (php/time)
     :clj   (/ (System/currentTimeMillis) 1000)))

(defn platform []
  #?(:phel    "phel"
     :clj     "clojure"
     :cljs    "clojurescript"
     :default "unknown"))
```

> **Tip:** `.cljc` files can use either `\` or `.` as the namespace separator (`shared\utils` or `shared.utils`). Both forms resolve to the same namespace, so the dot form parses cleanly under Clojure too.

### Platform-specific dependencies

```phel
(ns app.http
  #?(:phel (:require [phel.json :as json])
     :clj  (:require [clojure.data.json :as json])))

(defn parse [s]
  #?(:phel (json/decode s)
     :clj  (json/read-str s)))
```

> Phel accepts both Clojure-style vector entries (`[phel.json :as json :refer [encode]]`) and the older list form (`phel\json :as json`) inside `:require`, so the same `(ns ...)` form parses on both sides.

### Conditional data structures

```phel
(def config
  {:name "my-app"
   :version "1.0"
   #?@(:phel [:runtime "php"
              :min-version "8.3"]
       :clj  [:runtime "jvm"
              :min-version "21"])})
```

### Inside control flow

Reader conditionals resolve at parse time, so they work naturally inside any form:

```phel
(if #?(:phel true :clj false)
  (println "Running on Phel!")
  (println "Running on Clojure!"))
; => "Running on Phel!"
```

## How it works

Reader conditionals are resolved during the **parsing phase** of compilation (Lexer -> **Parser** -> Analyzer -> Emitter). The parser:

1. Reads keyword-form pairs inside `#?()` or `#?@()`
2. Selects the `:phel` branch if present, otherwise `:default`
3. For `#?()`: replaces the entire conditional with the selected form
4. For `#?@()`: splices the selected collection's elements into the parent
5. If no branch matches: the conditional is dropped as trivia (like a comment)

Since this happens at parse time, the analyzer and emitter never see the reader conditional — they only see the selected form.

## Summary

| Syntax | Name | Behavior | Context |
|--------|------|----------|---------|
| `#?()` | Reader conditional | Selects one form by platform key | Anywhere |
| `#?@()` | Reader conditional splicing | Splices collection elements into parent | Inside collections only |

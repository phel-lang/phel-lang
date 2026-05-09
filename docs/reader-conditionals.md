# Reader Conditionals in Phel

Reader conditionals enable platform-specific code in shared source files. They resolve at parse time, before compilation. Phel selects the `:phel` branch, ignores other platforms (`:clj`, `:cljs`, ...), and falls back to `:default` when present.

This enables `.cljc` files shared between Phel, Clojure, and other Lisp dialects.

## Basic usage: `#?()`

Selects one form based on platform:

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

;; No matching branch: form is dropped entirely
#?(:clj 99 :cljs 88)
; => (nothing: treated as whitespace)
```

## Splicing: `#?@()`

Splices multiple elements from a collection into the surrounding form:

```phel
[1 #?@(:phel [2 3]) 4]
; => [1 2 3 4]

(php/array 0 #?@(:phel [1 2 3]) 4)
; => array(0, 1, 2, 3, 4)
```

The matched branch **must be a sequential collection** (vector or list). Its elements are spliced into the parent; the collection wrapper is removed.

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

`#?@()` is only valid inside a collection (list, vector, map, set). Top-level use is an error:

```phel
;; ERROR: Reader conditional splicing #?@() is not allowed at the top level
#?@(:phel [1 2])
```

## Use cases

### Cross-platform source files (`.cljc`)

Phel discovers and compiles `.cljc` files alongside `.phel` files:

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

> Phel accepts both vector entries (`[phel.json :as json :refer [encode]]`) and the older list form (`phel\json :as json`) inside `:require`, so the same `(ns ...)` form parses on both sides.

### Conditional data structures

```phel
(def config
  {:name "my-app"
   :version "1.0"
   #?@(:phel [:runtime "php"
              :min-version "8.4"]
       :clj  [:runtime "jvm"
              :min-version "21"])})
```

### Inside control flow

Reader conditionals resolve at parse time, so they work inside any form:

```phel
(if #?(:phel true :clj false)
  (println "Running on Phel!")
  (println "Running on Clojure!"))
; => "Running on Phel!"
```

## How it works

Reader conditionals resolve during the **parsing phase** (Lexer -> **Parser** -> Analyzer -> Emitter). The parser:

1. Reads keyword-form pairs inside `#?()` or `#?@()`
2. Selects `:phel` if present, else `:default`
3. `#?()`: replaces the conditional with the selected form
4. `#?@()`: splices the selected collection into the parent
5. No match: drops the conditional as trivia

The analyzer and emitter only see the selected form.

## Summary

| Syntax | Name | Behavior | Context |
|--------|------|----------|---------|
| `#?()` | Reader conditional | Selects one form by platform key | Anywhere |
| `#?@()` | Reader conditional splicing | Splices collection elements into parent | Inside collections only |

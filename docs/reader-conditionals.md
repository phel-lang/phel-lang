# Reader Conditionals in Phel

Reader conditionals enable platform-specific code in shared source files. They resolve at parse time, before compilation. Phel selects the `:phel` branch, ignores other platforms (`:clj`, `:cljs`, ...), and falls back to `:default` when present. This is what makes `.cljc` files shareable between Phel, Clojure, and other Lisp dialects.

## Basic usage: `#?()`

Selects one form based on platform:

```phel
#?(:phel  (php/time)
   :clj   (System/currentTimeMillis)
   :cljs  (js/Date.now))
; => In Phel: (php/time)
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

1. `:phel` is always selected if present, regardless of position.
2. `:default` is the fallback when no `:phel` branch exists.
3. If neither is present, the entire form is dropped (treated as whitespace).

```phel
#?(:default 0 :phel 42)   ; => 42  (:phel wins)
#?(:clj 99 :default 0)    ; => 0   (fallback)
#?(:clj 99 :cljs 88)      ; => nothing (dropped)
```

## Splicing: `#?@()`

Splices multiple elements from a collection into the surrounding form. The matched branch **must be a sequential collection** (vector or list); its elements are spliced in and the wrapper is removed.

```phel
[1 #?@(:phel [2 3]) 4]              ; => [1 2 3 4]
(php/array 0 #?@(:phel [1 2 3]) 4)  ; => array(0, 1, 2, 3, 4)

;; With fallback / no match
[1 #?@(:clj [8 9] :default [2 3]) 4] ; => [1 2 3 4]
[1 #?@(:clj [8 9]) 4]                ; => [1 4]
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
  #?(:phel (php/time)
     :clj  (/ (System/currentTimeMillis) 1000)))

(defn platform []
  #?(:phel    "phel"
     :clj     "clojure"
     :cljs    "clojurescript"
     :default "unknown"))
```

> **Tip:** `.cljc` files should use `.` as the namespace separator (`shared.utils`) so the file parses cleanly under Clojure too. The legacy `\` separator (`shared\utils`) still resolves but is deprecated.

### Platform-specific dependencies

```phel
(ns app.http
  #?(:phel (:require [phel.json :as json])
     :clj  (:require [clojure.data.json :as json])))

(defn parse [s]
  #?(:phel (json/decode s)
     :clj  (json/read-str s)))
```

> Phel accepts both vector entries (`[phel.json :as json :refer [encode]]`) and the list form (`phel.json :as json`) inside `:require`, so the same `(ns ...)` form parses on both sides.

### Conditional data structures

```phel
(def config
  {:name "my-app"
   :version "1.0"
   #?@(:phel [:runtime "php" :min-version "8.4"]
       :clj  [:runtime "jvm" :min-version "21"])})
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

1. Reads keyword-form pairs inside `#?()` or `#?@()`.
2. Selects `:phel` if present, else `:default`.
3. `#?()`: replaces the conditional with the selected form.
4. `#?@()`: splices the selected collection into the parent.
5. No match: drops the conditional as trivia.

The analyzer and emitter only see the selected form.

## Summary

| Syntax | Name | Behavior | Context |
|--------|------|----------|---------|
| `#?()` | Reader conditional | Selects one form by platform key | Anywhere |
| `#?@()` | Reader conditional splicing | Splices collection elements into parent | Inside collections only |

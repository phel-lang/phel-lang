# Write macros

`defmacro` runs at expand time; body returns a form, not a value. Only quasiquoted (`` ` ``) content survives to runtime.

## Basic

```phel
(defmacro unless [test & body]
  `(if (not ~test) (do ~@body)))

(unless false (println "fires"))   ; expands to (if (not false) (do (println ...)))
```

| Reader | Meaning |
|--------|---------|
| `` ` `` | quasiquote — emit form as data, but allow splicing |
| `~x` | unquote — splice value of `x` |
| `~@xs` | unquote-splice — splice each element of `xs` |
| `name#` | auto-gensym — fresh symbol unique to this expansion |

## Inspect expansions

```phel
(macroexpand-1 '(unless flag (print 1)))
(macroexpand   '(-> x f g))
```

## Hygiene rules

Phel macros are **non-hygienic**. Two failure modes:

### 1. Local `let` shadows a referenced global

```phel
;; BAD: local `memoize-lru` shadows core fn
(defmacro defn-builder [name meta & fdecl]
  (let [memoize-lru (php/aget meta :memoize-lru)]
    `(def ~name ~meta (memoize-lru (fn ~@fdecl) ~memoize-lru))))

;; GOOD: role-suffix local names
(defmacro defn-builder [name meta & fdecl]
  (let [memoize-lru-arg (php/aget meta :memoize-lru)]
    `(def ~name ~meta (memoize-lru (fn ~@fdecl) ~memoize-lru-arg))))
```

Convention: suffix macro-local bindings with `-arg`, `-flag`, `-val`, `-sym`, `-form`.

### 2. Introduced symbols capture caller bindings

```phel
;; BAD: user's `acc` gets clobbered
(defmacro with-acc [& body] `(let [acc 0] ~@body))

;; GOOD: auto-gensym
(defmacro with-acc [& body] `(let [acc# 0] ~@body))
```

Hygiene checklist:

- List every `let`-binding name in the macro body.
- Grep each against in-scope globals (own ns, `phel\core`, `:use`d modules).
- Collision → suffix.
- New scope-introduced symbol → `name#`.
- Add a test exercising recursion / self-reference where shadowing diverges from the global.

## Expand-time vs runtime

```phel
(defmacro log-and [expr]
  (println "expanding" expr)    ; runs at compile time, prints once
  `(do (println "running" '~expr) ~expr))
```

Anything outside `` ` `` is evaluated when the macro fires, not when the caller runs.

## See also

- `docs/patterns.md` § Writing Macros
- `tasks/debug-errors.md` § Macro surprises
- `RULES.md` § New features (records / protocols / multimethods as alternatives when a fn or protocol would suffice)

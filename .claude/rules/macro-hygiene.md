---
description: Macro-hygiene pitfalls for quasiquote, gensym, and expand-time evaluation
globs: src/phel/**,tests/phel/**
---

# Macro Hygiene

Phel macros are non-hygienic: symbols inside `` ` `` quasiquote resolve in the **caller's** namespace, but `let`/`binding` names introduced in the **macro body** can shadow homonymous globals when unquoted. Failures are silent — wrong expansion, no error.

## Rule 1: Local bindings must not share names with referenced globals

Inside a macro body, every `let`-binding name is a candidate shadow. If the same name appears as a function call inside an unquoted template form, the local value wins.

### Before/after — `defn-builder` shadow (commit `85be1479`)

Buggy: local `memoize-lru` shadows global `memoize-lru` fn:

```phel
(let [memoize-flag (php/aget meta :memoize)
      memoize-lru  (php/aget meta :memoize-lru)]   ; <-- local binds same name as global fn
  (if memoize-lru
    `(def ~name ~meta (memoize-lru (fn ~@fdecl) ~memoize-lru))))
                       ;^^^^^^^^^^^                ^^^^^^^^^^^
                       ; reads local value, not global function
```

Fix: rename local with a role suffix:

```phel
(let [memoize-flag    (php/aget meta :memoize)
      memoize-lru-arg (php/aget meta :memoize-lru)]
  (if memoize-lru-arg
    `(def ~name ~meta (memoize-lru (fn ~@fdecl) ~memoize-lru-arg))))
```

### Naming conventions for macro-local bindings

- Suffix by role: `-arg`, `-flag`, `-val`, `-sym`, `-form`
- Or use auto-gensym `name#` for symbols that must be fresh in the expansion

## Rule 2: Use auto-gensym for new symbols introduced into the expansion

Any symbol the macro **invents** for the user's runtime scope (loop vars, accumulators, temp bindings) must be gensym'd, otherwise it can capture or be captured by user code.

```phel
;; bad — `acc` collides with user's `acc`
`(let [acc 0] ~@body)

;; good
`(let [acc# 0] ~@body)
```

## Rule 3: Expand-time vs runtime

- Macro body runs at **expand-time**; only forms inside `` ` `` reach runtime.
- Don't rely on runtime values inside the body — they don't exist yet.
- Computed values from the body can be spliced via `~`, but the **identifier** they evaluate to (a symbol/keyword) must still resolve correctly in the caller.

## Pre-commit checklist for `defmacro` / `defn-builder`-style expanders

- [ ] List every `let`-binding name in the macro body
- [ ] Grep each name against in-scope globals (own ns, `phel\core`, `use`d modules)
- [ ] Any collision: rename local with role suffix or `#` auto-gensym
- [ ] Any new symbol introduced into the expansion's scope: `name#`
- [ ] Add a Phel test that **calls** the macro recursively or in a context where shadowing would diverge from the global behavior (recursion, self-reference, mutual call)

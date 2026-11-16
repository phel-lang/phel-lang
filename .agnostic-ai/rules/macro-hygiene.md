---
name: macro-hygiene
description: 'Load when writing or changing a Phel macro: quasiquote, gensym and expand-time evaluation pitfalls.'
scope: src/phel
globs:
  - tests/phel/**
---

# Macro Hygiene

Phel macros are not hygienic. A `let` or `binding` name in a macro body silently shadows a global of the same name wherever the quasiquoted template refers to it. There is no error; the expansion is just wrong.

## Local names must not collide with referenced globals

From `defn-builder` in `src/phel/core/defs.phel`:

```phel
;; bad: the local `memoize-lru` shadows the global fn the template calls
(let [memoize-lru (php/aget meta :memoize-lru)]
  `(def ~name ~meta (memoize-lru (fn ~@fdecl) ~memoize-lru)))

;; good: a role suffix avoids the shadow
(let [memoize-lru-arg (php/aget meta :memoize-lru)]
  `(def ~name ~meta (memoize-lru (fn ~@fdecl) ~memoize-lru-arg)))
```

Suffix macro-local bindings with their role: `-arg`, `-flag`, `-val`, `-sym`, `-form`.

## Symbols the expansion introduces need auto-gensym

```phel
`(let [acc 0] ~@body)    ; bad: captures the caller's `acc`
`(let [acc# 0] ~@body)   ; good
```

## Expand time vs runtime

The macro body runs at expand time; only forms inside `` ` `` reach runtime. Splice computed values with `~`. Identifiers in the template must still resolve in the caller's namespace.

## Checklist

- Check every `let` name in the body against globals in scope (own namespace, `phel.core`, required namespaces). On a collision, add a suffix or use `name#`.
- Give every symbol the expansion binds a `name#`.
- Add a test where the shadow would diverge from the global, such as recursion or self-reference.

# Schema Guide

`phel\schema` provides data-driven schemas for validating, coercing, and generating values. Schemas are plain Phel data (keywords or vectors); there is no DSL to parse.

## Contents

- [Quickstart](#quickstart)
- [Schema kinds](#schema-kinds)
- [Core operations](#core-operations)
- [Named-schema registry](#named-schema-registry)
- [Function instrumentation](#function-instrumentation)
- [Pitfalls](#pitfalls)

## Quickstart

```phel
(ns my-app\main
  (:require phel\schema :as s))

(def User
  [:map {:closed? true}
   [:id    :int]
   [:email [:re #"^[^@]+@[^@]+$"]]
   [:age   [:maybe :int]]])

(s/validate User {:id 1 :email "a@b.co"})   ; => true
(s/explain  User {:id "x" :email "a@b"})    ; => {:errors [...]}
(s/coerce   User {"id" "1" "email" "a@b.co"})
```

## Schema kinds

| Kind | Example |
|------|---------|
| scalar | `:int`, `:string`, `:bool`, `:keyword`, `:any` |
| collection | `[:vector :int]`, `[:set :string]`, `[:map-of :keyword :int]` |
| map | `[:map [:k :int] [:k2 :string]]` |
| tuple | `[:tuple :int :string]` |
| choice | `[:enum :a :b]`, `[:or :int :string]`, `[:and :int pos-int?]`, `[:maybe :int]` |
| regex | `[:re #"pattern"]` |
| predicate | `[:fn even?]` |
| reference | `[:ref :my/User]` |
| function | `[[:=> [:int :int] :int]]` |

## Core operations

| Fn | Purpose |
|----|---------|
| `(validate schema value)` | boolean |
| `(explain schema value)` | `nil` on success, map on failure |
| `(conform schema value)` | coerced value or `:phel.schema/invalid` |
| `(coerce schema value)` | type-coerce to required shape |
| `(generate schema)` | random value conforming to schema |

## Named-schema registry

```phel
(s/register! :my/User User)
(s/validate [:ref :my/User] {:id 1 :email "a@b.co"})
```

## Function instrumentation

```phel
(defn add [a b] (+ a b))
(s/instrument! 'add add [:=> [:int :int] :int])
;; (add "x" 2)  ; throws with schema failure
```

## Pitfalls

- `:map` is open by default; add `{:closed? true}` to reject extra keys
- `[:re ...]` expects a `#"regex"` literal, not a string
- `generate` may fail for over-constrained `[:and ...]` or `[:re ...]` schemas; add a `[:gen ...]` hint if needed

## See also

- `phel\match` for destructuring matched shapes
- `phel\test\gen` for property-based testing driven by schemas

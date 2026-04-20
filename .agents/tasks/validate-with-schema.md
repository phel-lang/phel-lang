# Validate with schema

`phel\schema` validates, coerces, and generates values against data-driven schemas.

## Define a schema

```phel
(ns my-app\main
  (:require phel\schema :as s))

(def User
  [:map {:closed? true}
   [:id    :int]
   [:email [:re #"^[^@]+@[^@]+$"]]
   [:age   [:maybe :int]]])
```

## Common operations

```phel
(s/validate User {:id 1 :email "a@b.co"})   ; => true
(s/explain  User {:id "x" :email "a@b"})    ; => {:errors [...]}
(s/coerce   User {"id" "1" "email" "a@b.co"})
```

## Instrument a function

```phel
(defn add [a b] (+ a b))
(s/instrument! 'add add [:=> [:int :int] :int])
;; (add "x" 2) ; throws with explain data
```

## Common kinds

| Kind | Example |
|------|---------|
| scalar | `:int`, `:string`, `:bool`, `:any` |
| collection | `[:vector :int]`, `[:map-of :keyword :int]` |
| map | `[:map [:k :int]]` |
| choice | `[:or :int :string]`, `[:enum :a :b]`, `[:maybe :int]` |
| regex | `[:re #"pattern"]` |
| predicate | `[:fn pos?]` |

## Gotchas

- `:map` is open by default; add `{:closed? true}` to reject extras
- `[:re #"..."]` expects a regex literal, not a string
- `generate` may diverge on tight `[:and ...]` or `[:re ...]`; provide a `:gen` hint

## See also

- `docs/schema-guide.md` for full reference
- `use-core-lib.md` for combining with `get-in`, `update-in`

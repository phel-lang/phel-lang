# Schema Guide

`phel.schema` validates, coerces, and generates values. Schemas are plain Phel data (keywords or vectors), no DSL.

## Quickstart

```phel
(ns my-app.main
  (:require phel.schema :as s))

(def User
  [:map {:closed true}
   [:id    :int]
   [:email [:re #"^[^@]+@[^@]+$"]]
   [:age   [:maybe :int]]])

(s/validate User {:id 1 :email "a@b.co" :age nil})   ; => true
(s/explain  User {:id "x" :email "a@b"})             ; => {:schema User :value {...} :errors [...]}
(s/coerce   User {"id" "1" "email" "a@b.co" "age" nil})
```

`[:maybe T]` makes the *value* nilable but the key is still required-present; omit the key on a `{:closed true}` map and validation fails with `:type :missing`.

## Schema kinds

| Kind | Example |
|------|---------|
| scalar | `:int`, `:string`, `:bool`, `:keyword`, `:any` |
| collection | `[:vector :int]`, `[:set :string]`, `[:map-of :keyword :int]` |
| map | `[:map [:k :int] [:k2 :string]]` |
| tuple | `[:tuple :int :string]` |
| choice | `[:enum :a :b]`, `[:or :int :string]`, `[:and :int [:fn pos-int?]]`, `[:maybe :int]` |
| regex | `[:re #"pattern"]` |
| predicate | `[:fn even?]` |
| reference | `[:ref :my/User]` |
| function | `[:=> [:int :int] :int]` |

## Core operations

| Fn | Purpose |
|----|---------|
| `(validate schema value)` | boolean |
| `(explain schema value)` | `nil` on success, `{:schema s :value v :errors [...]}` on failure |
| `(conform schema value)` | coerced value or `:phel.schema/invalid` |
| `(coerce schema value)` | type-coerce to required shape |
| `(generate schema)` | random value conforming to schema |

## Named-schema registry

```phel
(s/register! :my/User User)
(s/validate [:ref :my/User] {:id 1 :email "a@b.co" :age nil})
```

## Function instrumentation

```phel
(defn ^int add [^int a ^int b] (+ a b))
(s/instrument! :add add [:=> [:int :int] :int])
;; (add "x" 2)  ; throws with schema failure
```

## Pitfalls

- `:map` is open by default; add `{:closed true}` to reject extra keys (note: `:closed`, not `:closed?`; a `?` key is silently ignored)
- `[:maybe T]` allows a nil value but does not make the key optional; use `{:optional true}` on the entry for that
- `[:and ...]` children must be schemas; wrap a bare predicate as `[:fn pred]` (e.g. `[:fn pos-int?]`, not `pos-int?`)
- `[:re ...]` expects a `#"regex"` literal (or a PCRE string *with* delimiters, e.g. `"/^[0-9]+$/"`); a bare pattern string like `"^[0-9]+$"` silently fails
- `generate` may fail on over-constrained `[:and ...]` or `[:re ...]` schemas; pass `{:gen <gen-fn>}` in schema options to override

## See also

- `phel.match` for destructuring matched shapes
- `phel.test.gen` for property-based testing driven by schemas

---

📖 **Full guide:** [schema API on phel-lang.org](https://phel-lang.org/documentation/reference/api/schema/)

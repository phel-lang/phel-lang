# Core library

Verify with `(doc <fn>)` before guessing.

## Build

```phel
[1 2 3]       {:a 1 :b 2}       #{1 2 3}
(vector 1 2)  (hash-map :a 1)   (set [1 2 2])
(range 5)     (repeat 3 :x)
```

## Access

| Op | Fn |
|----|----|
| Lookup (+ default) | `(get m k d)`, `(get-in m [k1 k2] d)` |
| Ends | `first`, `rest`, `last`, `(nth v i d)` |
| Meta | `count`, `empty?`, `seq`, `keys`, `vals` |
| Membership | `(contains? m k)` (on vector: index range) |

## Modify (returns new)

| Op | Fn |
|----|----|
| Add | `(conj coll x)` — vec: tail, map: `[k v]`, set: member |
| Set | `(assoc m k v)`, `(assoc-in m [k1 k2] v)` |
| Remove | `(dissoc m k)` |
| Update | `(update m k f args...)`, `(update-in m [k1 k2] f)` |
| Merge | `(merge m1 m2)` — right wins |

## Transform

```phel
(map f xs)  (map-indexed (fn [i x] ...) xs)
(filter pred xs)  (remove pred xs)  (keep f xs)
(reduce f init xs)
(take n xs)  (drop n xs)  (take-while p xs)  (drop-while p xs)
(partition n xs)  (partition-all n xs)
(group-by f xs)  (zipmap ks vs)
(distinct xs)  (reverse xs)
(sort xs)  (sort-by f xs)
```

## Control flow

| Form | Use |
|------|-----|
| `if`, `if-not`, `when`, `when-not` | branch |
| `when-let`, `if-let` | bind + branch |
| `cond` | predicate cascade |
| `condp` | dispatch on predicate against value |
| `case` | literal equality, no eval |
| `do` | side-effect sequence |

Threading forms in [`RULES.md`](../RULES.md).

## Predicates

```phel
nil? some? empty? contains?
even? odd? pos? neg? zero?
int? float? number? string? keyword? symbol?
map? vector? set? list? seq? sequential? coll?
fn? ifn?
```

## State

```phel
(def a (atom 0))
@a                      ; deref
(swap! a inc)
(swap! a + 5)
(reset! a 0)
(compare-and-set! a old new)

(def ^:dynamic *debug* false)
(binding [*debug* true] (log "x"))
```

## Strings (`phel\string :as str`)

```phel
(str/split s #",")    (str/join "-" xs)
(str/upper-case s)    (str/lower-case s)
(str/trim s)          (str/replace s "a" "b")
(str/contains? s sub) (str/starts-with? s p) (str/ends-with? s p)
(str/blank? s)
```

Core: `(str a b c)`, `(name :k)`, `(keyword "x")`, `(symbol "x")`.

## IO + serialisation

- `println`, `print`, `(pr-str x)`
- `phel\json`: `(json/encode m)`, `(json/decode s)`
- `phel\pprint`: `(pprint x)`
- `phel\walk`: `(postwalk f m)`, `(prewalk f m)`
- `phel\base64`: `(encode s)`, `(decode s)`, `(encode-url s)`, `(decode-url s)`
- File: `(php/file_get_contents p)`, `(php/file_put_contents p s)`

## Bundled modules

| Module | Purpose |
|--------|---------|
| `phel\core` | collections, threading, state, predicates |
| `phel\string` (`:as str`) | string ops |
| `phel\json` | encode / decode |
| `phel\pprint` | pretty-print |
| `phel\walk` | tree traversal |
| `phel\base64` | base64 encode/decode (URL-safe variants) |
| `phel\http` | request/response structs, `request-from-globals`, `emit-response` |
| `phel\router` | nested routes, middleware, URL gen, `compiled-router` |
| `phel\http-client` | outbound HTTP: `get`, `post`, `put`, `patch`, `delete`, `head` |
| `phel\cli` | Symfony-console wrapper — see `tasks/cli-tool.md` |
| `phel\html` | HTML templating |
| `phel\test` | `deftest`, `is`, `are`, `testing`, fixtures |
| `phel\mock` | `with-mocks`, `mock`, `spy`, `called-with?` |
| `phel\async` | `future`, `async`, `await`, `delay` (AMPHP-backed fibers) |
| `phel\ai` | LLM client (`complete`, `chat`, `configure`) |
| `phel\repl` | REPL utilities |

## When to use what

| Situation | Pick |
|-----------|------|
| Filter → map → reduce | `->>` |
| Sequential map/object updates | `->` |
| Optional chain | `some->` / `some->>` |
| Conditional build-up | `cond->` |
| Multi-branch on literals | `case` |
| Multi-branch on predicates | `cond` |
| Long-lived mutable | `atom` + `swap!` |

## When unsure

```bash
./vendor/bin/phel doc <fn>
git grep 'defn <fn>' src/phel/core
```

## Next

`docs/patterns.md`, `docs/data-structures-guide.md`, `docs/lazy-sequences.md`, `docs/transducers.md`, `docs/cli-guide.md`, `tasks/http-app.md`, `tasks/cli-tool.md`

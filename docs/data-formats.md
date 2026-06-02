# Data Interchange Formats

Phel ships two eval-free interchange modules for talking to other Clojure-aligned runtimes (or to itself across process boundaries):

- [`phel.edn`](#phel-edn): [Extensible Data Notation](https://github.com/edn-format/edn), the Clojure-native subset of reader syntax.
- [`phel.transit`](#phel-transit): [Transit](https://github.com/cognitect/transit-format) layered on top of JSON.

Neither goes through `eval`, so both are safe to point at untrusted input.

## `phel.edn`

### Public API

| Function | Purpose |
|---|---|
| `(read-string s)` / `(read-string s opts)` | Read the first EDN form from `s`. |
| `(read-string-all s)` / `(read-string-all s opts)` | Read every form; returns a vector. |
| `(write-string v)` | Serialise `v` to EDN. |
| `(write-string-all xs)` | Serialise a sequence; values joined by a single space. |

### Options map

| Key | Value | Behaviour |
|---|---|---|
| `:readers` | `{tag fn}` | Per-call tag handlers. Tags may be symbols, keywords, or strings; the handler receives the already-parsed form. The global `TagRegistry` is snapshotted before the call and restored in a `finally`. |
| `:eof` | any | Returned by `read-string` when input is empty, whitespace-only, or comment-only (default `nil`). |

### Type mapping

`phel.edn` delegates reading to Phel's own reader and writing to `Printer::readable()`, so every value Phel can print readably round-trips: nil, booleans, integers, floats, strings, characters, keywords, symbols, lists, vectors, maps, sets, the `#_` discard form, `;` comments, and built-in tagged literals (`#uuid`, `#inst`, `#regex`).

The lexer accepts EDN's full tag grammar, so namespaced tags (`#my.app/Person`, `#myapp/double`, `#my.app.module`) lex as a single tag. Unqualified tags (`#uuid`, `#inst`) work unchanged.

### Examples

```phel
(ns my-app.config
  (:require phel.edn :as edn))

(edn/read-string "{:host \"localhost\" :port 4000}")
;; => {:host "localhost" :port 4000}

(edn/read-string-all "1 2 3")
;; => [1 2 3]

(edn/write-string {:users [{:name "Alice"} {:name "Bob"}]})
;; => "{:users [{:name \"Alice\"} {:name \"Bob\"}]}"

;; Custom tag: register a handler for #my.app/Point literals
(edn/read-string "#my.app/Point [1 2]"
                 {:readers {'my.app/Point (fn [[x y]] {:x x :y y})}})
;; => {:x 1 :y 2}

;; Empty input with a sentinel
(edn/read-string "" {:eof :no-data})
;; => :no-data
```

## `phel.transit`

### Public API

| Function | Purpose |
|---|---|
| `(read-string s)` / `(read-string s opts)` | Decode one Transit+JSON-Verbose value. |
| `(write-string v)` | Encode `v` to a Transit+JSON-Verbose string. |

This first iteration implements **JSON-Verbose** only: maps with string keys serialise to ordinary JSON objects (no key caching); any other map serialises as a `~#cmap`.

### Options map

| Key | Value | Behaviour |
|---|---|---|
| `:handlers` | `{tag-string fn}` | Decoder for `~#tag` arrays. The handler receives the already-decoded representation. |
| `:default-handler` | `(fn [tag rep] …)` | Fallback for any unknown tag (scalar or array). Receives the bare tag name and decoded rep. Without it, unknown tags throw `InvalidArgumentException`. |

### Type mapping

| Phel | Transit JSON-Verbose |
|---|---|
| `nil` | `null` |
| bool | `true` / `false` |
| int, float | JSON number |
| string | JSON string (with `~`/`^`/`` ` `` escape) |
| keyword | `"~:name"` |
| symbol | `"~$name"` |
| `Phel\Lang\UUID` | `"~uXXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"` |
| `DateTimeImmutable` | `"~tISO-8601"` |
| vector | JSON array `[…]` |
| list | `["~#list", [...]]` |
| set | `["~#set", [...]]` |
| map (string keys) | JSON object `{...}` |
| map (composite keys) | `["~#cmap", [k1, v1, k2, v2, …]]` |

Plain Phel strings whose first character is `~`, `^`, or `` ` `` are escaped on write with a leading `~`, so the reader does not mistake them for a Transit tag.

### Examples

```phel
(ns my-app.api
  (:require phel.transit :as transit))

;; String-keyed map -> plain JSON object
(transit/write-string {"name" "Alice"})
;; => "{\"name\":\"Alice\"}"

;; Keyword keys are composite keys, so the map becomes a ~#cmap
(transit/write-string {:status :ok :count 42})
;; => "[\"~#cmap\",[\"~:status\",\"~:ok\",\"~:count\",42]]"

;; Reading a string-keyed object yields keyword keys back
(transit/read-string "{\"~:status\":\"~:ok\",\"~:count\":42}")
;; => {:status :ok :count 42}

;; Custom extension type (read-side)
(transit/read-string "[\"~#point\",[1,2]]"
                     {:handlers {"point" (fn [[x y]] {:x x :y y})}})
;; => {:x 1 :y 2}

;; Catch-all for unknown tags
(transit/read-string "[\"~#myapp/Foo\",[1,2]]"
                     {:default-handler (fn [tag rep] [:unknown tag rep])})
;; => [:unknown "myapp/Foo" [1 2]]
```

### Intentionally out of scope (for now)

- **Cached keys** (the non-verbose Transit+JSON encoding with `^` cache markers).
- **Transit+MessagePack**.
- **Write-side extension hooks**: there is no symmetric `:handlers` map on `write-string` yet. Encode custom types yourself before handing them to `write-string`.

## Choosing between EDN and Transit

| Need | Use |
|---|---|
| Phel-to-Phel config files, source-like data | EDN |
| Talking to Clojure/Java/JS over HTTP, MessagePack-ready wire format | Transit |
| Human-edited input with comments and `#_` discards | EDN |
| Round-tripping JSON-shaped data with rich types | Transit |

---

📖 **Full guide:** [edn / transit / json API on phel-lang.org](https://phel-lang.org/documentation/reference/api/edn/)

# Runtime

What emitted PHP runs against. `src/php/Lang/` plus `src/Phel.php` (the public static facade). Independent of `Compiler/`: usable from plain PHP.

## Shape of emitted code

```php
\Phel::addDefinition(
    "user",
    "greet",
    function ($name) { return (\Phel::getDefinition("phel.core", "str"))("hello, ", $name); },
    \Phel::map(\Phel::keyword("doc"), "Greet someone."),
);
```

Three concerns: types, per-namespace `Registry`, value equality + hashing.

## Core types

| Type | Notes |
|------|-------|
| `Symbol` | Identifier with optional ns. `NAME_*` constants name special forms. |
| `Keyword` | Interned `:foo`. Implements `FnInterface`, so `(:foo m)` is a map lookup. |
| `Atom` | Mutable cell with watches/validators. `(atom v)` and `swap!`/`reset!` produce/mutate it. |
| `Delay` | One-shot lazy value (not a sequence). |
| `Volatile` | Mutable box for transducer state. |
| `Reduced` | Early-termination sentinel for `reduce`/`transduce`. |
| `Future` | Amphp-backed future wrapper; `deref` awaits inside a fiber. Used by `async`/`await` (see `src/phel/core/async.phel`). |
| `SourceLocation` | File + line + column on every readable form. |

All types implement `TypeInterface` (composes `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`).

## Persistent collections (`Lang/Collections/`)

Immutable. "Modify" returns a new value with structural sharing.

| Type | Impl |
|------|------|
| Vector | `PersistentVector`: 32-way trie |
| Map | `PersistentArrayMap` (small) promoted to `PersistentHashMap` (HAMT) |
| List | `PersistentList` (singly linked) |
| Set | `PersistentHashSet` (over hash map) |
| Lazy seq | `LazySeq`: realise + cache per element |
| Struct | `AbstractPersistentStruct`: fixed-key map, subclassed by `defstruct` |

Transients for bulk building: `transient`, mutate, `persistent!`. Never let a transient escape its scope.

`TypeFactory` (singleton): `persistentVectorFromArray()`, `persistentMapFromKVs()`, `persistentHashSetFromArray()`. Compiler emits via `\Phel::vector(...)`, `\Phel::map(...)`, `\Phel::set(...)`.

## Equality + hashing

Value equality: `(= [1 2] [1 2])` is true regardless of object identity.

- `Equalizer`: `===` for scalars, structural for collections.
- `Hasher`: `int` hashes that agree with `Equalizer`. A mismatch loses map entries.

Built-in types participate. PHP objects fall back to `spl_object_hash` (identity).

## Registry vs GlobalEnvironment

| | When | Stores |
|--|------|--------|
| `Lang\Registry` (singleton) | runtime | `ns → name → value` + metadata |
| `GlobalEnvironment` (`Compiler/Domain/Analyzer/Environment/`) | compile time | what analyzer knows about declared names |

Each top-level form compiles + evaluates before the next is analysed, so both stay in sync. `defmacro` becomes available immediately to following forms. Reset both with `CompilerFacade::resetGlobalEnvironment()`.

## `\Phel` static facade

`src/Phel.php` is the runtime ABI. Cached `.php` files in the wild call into it. Signature changes are breaking.

- `addDefinition($ns, $name, $value, $meta = null)` (delegated to `Registry` via `__callStatic`)
- `keyword($name, $namespace = null)` / `symbol($name)`
- `vector(?array $values = [])` / `set(?array $values = [])` / `map(...$kvs)`

Changing a signature requires auditing `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/*Emitter.php`.

## What embedding costs

A PHP application that calls Phel pays on three lines, and only the third is
per call. One snapshot, PHP 8.4 on macOS, warm `.phel/cache`, calling
`phel.core/str`:

| | no opcache | opcache file cache |
|---|---|---|
| `vendor/autoload.php` | 11ms | 8ms |
| `Phel::bootstrap()` | 5ms | 4ms |
| load `phel.core` | 491ms | 37ms |
| **first call reachable after** | **508ms** | **49ms** |
| peak memory | 48MB | 28MB |
| per call after that | 0.2µs | 0.2µs |

The shape matters more than the figures. The boundary is free, the load is
everything, and opcache is worth more than an order of magnitude on it, which
is why [`phel doctor`](../cli-reference.md) checks for it. A host that boots
per request (PHP-FPM) pays the whole column; a worker runtime pays it once.

Reproduce with any namespace of your own:

```php
require 'vendor/autoload.php';
Phel\Phel::bootstrap(__DIR__);
$t = hrtime(true);
new Phel\Run\RunFacade()->runNamespace('phel.core');
printf("%.1f ms, %.1f MB\n", (hrtime(true) - $t) / 1e6, memory_get_peak_usage(true) / 1048576);
```

Two of the three lines are gated: `Run/ReplBootBench` guards namespace
loading, `Interop/ExportedCallBench` guards the per-call boundary. Bootstrap
is not gated, because it memoizes and cannot be re-entered in one process
([benchmarks.md](benchmarks.md)).

Deployment shapes for each, worker runtimes included:
<https://phel-lang.org/documentation/deployment/>.

## Reader tags (`#tag`)

`Lang/TagHandlers/` implementations registered in `Lang/TagRegistry.php`. Built-ins: `#inst` (`InstTagHandler`), `#regex` (`RegexTagHandler`), `#uuid` (`UUIDTagHandler`). The `#php` tag is handled directly in the reader, not via `TagRegistry`. Add custom tags: `TagRegistry::register('mything', new MyHandler())`.

## Source locations

Carried lexer to AST to emitted source map. Don't drop. When constructing a form inside a special-form handler, use `Symbol::copyLocationFrom($nearby)`. Examples throughout `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`.

## See also

- `src/php/Lang/CLAUDE.md`
- [Data structures](https://phel-lang.org/documentation/language/data-structures/): user view
- [compiler.md](compiler.md): emit path

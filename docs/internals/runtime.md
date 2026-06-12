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

## Reader tags (`#tag`)

`Lang/TagHandlers/` implementations registered in `Lang/TagRegistry.php`. Built-ins: `#inst` (`InstTagHandler`), `#regex` (`RegexTagHandler`), `#uuid` (`UUIDTagHandler`). The `#php` tag is handled directly in the reader, not via `TagRegistry`. Add custom tags: `TagRegistry::register('mything', new MyHandler())`.

## Source locations

Carried lexer to AST to emitted source map. Don't drop. When constructing a form inside a special-form handler, use `Symbol::copyLocationFrom($nearby)`. Examples throughout `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`.

## See also

- `src/php/Lang/CLAUDE.md`
- [Data structures](https://phel-lang.org/documentation/language/data-structures/): user view
- [compiler.md](compiler.md): emit path

# Runtime

What emitted PHP runs against. `src/php/Lang/` plus `src/php/Phel.php`. Independent of `Compiler/`: usable from plain PHP.

## Shape of emitted code

```php
\Phel::addDefinition(
    "user",
    "greet",
    function ($name) { return \phel\core\str("hello, ", $name); },
    \Phel::map(\Phel::keyword("doc"), "Greet someone."),
);
```

Three concerns: types, per-namespace `Registry`, value equality + hashing.

## Core types

| Type | Notes |
|------|-------|
| `Symbol` | Identifier with optional ns. `NAME_*` constants name special forms. |
| `Keyword` | Interned `:foo`. Implements `FnInterface` → `(:foo m)` is a map lookup. |
| `Variable` | Mutable cell with watches/validators; `def` wraps values in one. |
| `Delay` | One-shot lazy value (not a sequence). |
| `Volatile` | Mutable box for transducer state. |
| `Reduced` | Early-termination sentinel for `reduce`/`transduce`. |
| `PhelFuture` | Promise/future for `Fiber/`. |
| `SourceLocation` | File + line + column on every readable form. |

All types implement `TypeInterface` (composes `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`).

## Persistent collections (`Lang/Collections/`)

Immutable; "modify" returns a new value with structural sharing.

| Type | Impl |
|------|------|
| Vector | `PersistentVector`: 32-way trie |
| Map | `PersistentArrayMap` (small) → `PersistentHashMap` (HAMT) |
| List | `PersistentList` (singly linked) |
| Set | `PersistentHashSet` (over hash map) |
| Lazy seq | `LazySeq`: realise + cache per element |
| Struct | `AbstractPersistentStruct`: fixed-key map, subclassed by `defstruct` |

Transients for bulk building: `as-transient` → mutate → `persistent!`. Never let a transient escape its scope.

`TypeFactory` (singleton): `emptyVector()`, `emptyMap()`, `vectorFromArray()`. Compiler emits via `\Phel::vector(...)`, `\Phel::map(...)`, `\Phel::set(...)`.

## Equality + hashing

Value equality: `(= [1 2] [1 2])` true regardless of object identity.

- `Equalizer`: `===` for scalars, structural for collections.
- `Hasher`: `int` hashes that agree with `Equalizer`. Mismatch = lost map entries.

Built-in types participate. PHP objects fall back to `spl_object_hash` (identity).

## Registry vs GlobalEnvironment

| | When | Stores |
|--|------|--------|
| `Lang\Registry` (singleton) | runtime | `ns → name → value` + metadata |
| `GlobalEnvironment` (`Compiler/Domain/Analyzer/Environment/`) | compile time | what analyzer knows about declared names |

Each top-level form compiles + evaluates before the next is analysed → both stay in sync. `defmacro` becomes available immediately to following forms. Reset both: `CompilerFacade::resetGlobalEnvironment()`.

## `\Phel` static facade

`src/php/Phel.php` is the runtime ABI. Cached `.php` files in the wild call into it. Signature changes = breaking.

- `addDefinition($ns, $name, $value, $meta = null)`
- `keyword($name)` / `namespacedKeyword($ns, $name)` / `symbol($name)`
- `vector(...$items)` / `map(...$kvs)` / `set(...$items)`
- `ns($name)`: switch current namespace

Change a signature → audit `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/*Emitter.php`.

## Reader tags (`#tag`)

`Lang/TagHandlers/` implementations registered in `Lang/TagRegistry.php`. Built-ins: `#inst`, `#regex`, `#php`. Add: `TagRegistry::register('mything', new MyHandler())`.

## Source locations

Carried lexer → AST → emitted source map. Don't drop. When constructing a form inside a special-form handler, `Symbol::copyLocationFrom($nearby)` (examples throughout `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`).

## See also

- `src/php/Lang/CLAUDE.md`
- [data-structures-guide.md](../data-structures-guide.md): user view
- [compiler.md](compiler.md): emit path

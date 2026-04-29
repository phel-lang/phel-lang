# Runtime

What the emitted PHP actually runs against. Everything in `src/php/Lang/` is the runtime — types, persistent collections, the namespace registry, and the static `\Phel` facade that compiled code calls into.

## What "runtime" means here

The compiler produces PHP that looks roughly like this:

```php
\Phel::addDefinition(
    "user",
    "greet",
    function ($name) {
        return \phel\core\str("hello, ", $name);
    },
    \Phel::map(\Phel::keyword("doc"), "Greet someone."),
);
```

Three runtime concerns are visible here:

1. **Types** — `Phel\Lang\Keyword`, `Phel\Lang\Symbol`, `Phel\Lang\Collections\Map\PersistentArrayMap`, …
2. **Per-namespace registry** — `\Phel::addDefinition()` writes into `Lang\Registry`.
3. **Equality and hashing** — every collection key needs `equals()` and `hash()` that match Phel semantics, not PHP's.

The `Lang/` module never depends on `Compiler/`. You can use the runtime types from plain PHP code with no compiler involved.

## Core types

| Type | Purpose |
|------|---------|
| `Symbol` | Identifier with optional namespace. Special constants name language forms (`def`, `fn`, `if`, `recur`, …). Compared by name. |
| `Keyword` | Interned, immutable name (`:foo`). Implements `FnInterface` so `(:foo m)` works as a map lookup. |
| `Variable` | Mutable cell with watches and validators (`def`-defined values are wrapped in one). |
| `Delay` | One-shot lazy value — like `lazy-seq` but not a sequence. |
| `Volatile` | Lightweight mutable box; the canonical accumulator for transducers. |
| `Reduced` | Sentinel that signals early termination from a `reduce`/`transduce`. |
| `PhelFuture` | Promise/future used by `Fiber/`. |
| `SourceLocation` | File + line + column attached to every readable form. |

Every type implements `TypeInterface` which composes `MetaInterface`, `SourceLocationInterface`, `EqualsInterface`, `HashableInterface`. That is what lets you put any Phel value into a hash map and get it back out.

## Persistent collections

Collections live under `Lang/Collections/`. They are immutable; "modification" returns a new value sharing structure with the old.

| Collection | Implementation |
|------------|----------------|
| Vector | `PersistentVector` — 32-way trie, O(log32 n) random access |
| Hash map | `PersistentArrayMap` for small N, `PersistentHashMap` (HAMT) for larger |
| List | `PersistentList` (singly linked) |
| Hash set | `PersistentHashSet` (backed by a hash map) |
| Lazy seq | `LazySeq` — realises one element at a time, caches results |
| Struct | `AbstractPersistentStruct` — a fixed-key map; subclassed by `defstruct` |

Bulk building uses transients: `as-transient` returns a mutable variant, and `persistent!` snaps it back to immutable. Use this for hot paths inside a function — never let a transient escape.

`TypeFactory` (singleton, `getInstance()`) is the entry point: `emptyVector()`, `emptyMap()`, `vectorFromArray()`, etc. The compiler emits calls into it via `\Phel::vector(...)`, `\Phel::map(...)`, `\Phel::set(...)`.

## Equality and hashing

Phel equality is value equality — `(= [1 2] [1 2])` is true even if the two vectors are different PHP objects. Two pieces fit together:

- **`Equalizer`** — pairwise comparison; falls back to `===` for scalars, structural for collections.
- **`Hasher`** — produces `int` hashes that agree with `Equalizer`. Without this, hash maps would lose entries silently.

Anything you store as a map key has to participate in this protocol. The built-in types do. PHP objects you stuff into a Phel map use identity hashing by default (via `spl_object_hash`).

## Namespace registry

`Lang\Registry` (singleton) is the runtime side of `def`. It stores, per namespace, a map of `name → value` and a parallel map of `name → metadata`.

Important distinction:

- **`GlobalEnvironment`** (`Compiler/Domain/Analyzer/Environment/GlobalEnvironment.php`) is the *compile-time* picture — what the analyzer knows about declared names.
- **`Registry`** is the *runtime* picture — actual values currently bound.

When the compiler emits `\Phel::addDefinition("user", "greet", $fn, $meta)`, that hits the `Registry`. When the analyzer sees `(greet "x")` it uses `GlobalEnvironment`. The two are kept in sync because each top-level form is compiled and evaluated before the next one is analysed (see [compiler.md](compiler.md), section "Evaluator").

Reset both with `CompilerFacade::resetGlobalEnvironment()` if you need a clean slate (test isolation, REPL `:reset`).

## The `\Phel` static facade

`src/php/Phel.php` (top-level, not under `Lang/`) is the static surface that *generated* PHP calls. Treat it as the runtime ABI: changes here are breaking changes for every cached `.php` file in the wild.

The most-used members:

- `\Phel::addDefinition($ns, $name, $value, $meta = null)`
- `\Phel::keyword($name)` / `\Phel::namespacedKeyword($ns, $name)`
- `\Phel::symbol($name)`
- `\Phel::vector(...$items)` / `\Phel::map(...$kvs)` / `\Phel::set(...$items)`
- `\Phel::ns($name)` — switch the current namespace at runtime

If you change a method signature here, audit all `*Emitter.php` files under `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/` to match.

## TagHandlers and reader macros

Custom reader tags live in `Lang/TagHandlers/`. Each handler implements a small interface that takes a Phel form and returns one. They are looked up at read time via `Lang/TagRegistry.php`. Built-in handlers: `#inst`, `#regex`, `#php`. Adding `#mything` is a `TagRegistry::register('mything', new MyHandler())` call.

## Source locations

Every readable form carries a `SourceLocation` from the lexer all the way through the AST to the emitted PHP source map. Don't drop it: error messages, the LSP, and the linter all rely on it.

If you build a form by hand inside a special-form handler, copy the location from a nearby form with `Symbol::copyLocationFrom($other)` — there are dozens of examples in `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`.

## See also

- `src/php/Lang/CLAUDE.md` — module-level cheat sheet
- [data-structures-guide.md](../data-structures-guide.md) — user-facing perspective on the same collections
- [compiler.md](compiler.md) — how the compiler emits calls into this runtime

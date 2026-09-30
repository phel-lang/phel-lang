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
A transient map put does the same trie walk as a persistent one (no edit tokens), so it saves only the map wrapper per write.
Opening and closing costs about what eight writes save; for fewer, a multi-key `assoc` is as fast or faster.

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
per call. One snapshot, PHP 8.5 CLI on an Apple M4 Pro, a Composer project
that requires `phel-lang/phel-lang` and loads one namespace of its own
(`app.main`, which pulls in `phel.core`), medians (method after the recipe):

| | cold `.phel/cache` | warm, no opcache | warm, opcache file cache |
|---|---|---|---|
| `vendor/autoload.php` | 2ms | 2ms | 1ms |
| `Phel::bootstrap()` | 9ms | 5ms | 4ms |
| load the namespace | 1327ms | 48ms | 30ms |
| **first call reachable after** | **1339ms** | **56ms** | **35ms** |
| peak memory | 84MB | 18MB | 26MB |

The shape matters more than the figures. The call boundary is free
(`Interop/ExportedCallBench` measured 0.2µs per call when it landed), the load
is everything, and the load is only cheap once `.phel/cache` holds the
compiled namespaces. Opcache takes the warm load down again, which is why
[`phel doctor`](../cli-reference.md) checks for it. A host that boots per
request (PHP-FPM) pays the load on every request; a worker runtime pays it
once.

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

### Warm-up recipe

Three steps, each one measured below on the same machine as the table.

**1. Compile at deploy time.** `.phel/cache` does not survive a move: a copy
of the warm project in another directory recompiled from scratch on its first
load. Run the host's own
load lines once, from the directory the host will serve from, as the last
build step:

```php
<?php // warmup.php, next to vendor/
require __DIR__ . '/vendor/autoload.php';
Phel\Phel::bootstrap(__DIR__);
new Phel\Run\RunFacade()->runNamespace('app.main'); // your entry namespace
```

```bash
php warmup.php
```

Without it the first request pays the cold column (1.3s here, 84MB).

**2. CLI hosts: turn on the opcache file cache.** Opcache is off on the CLI by
default, and its shared memory dies with the process, so a CLI host needs the
file cache:

```ini
opcache.enable_cli=1
opcache.file_cache=/var/cache/php-opcache   ; any writable directory that exists
opcache.file_cache_only=1
```

| CLI process, warm `.phel/cache` | in script | whole process |
|---|---|---|
| no opcache | 56ms | 104ms |
| file cache, first run (fills it) | 161ms | 214ms |
| file cache, warm | 35ms | 84ms |
| `php -r ''` on this machine | | 48ms |

**3. PHP-FPM hosts: preload the same file.** FPM keeps opcache in shared memory
across requests, so only the first request after a restart compiles the
cached PHP. Point `opcache.preload` at the warm-up file and that request is
warm too:

```ini
opcache.enable=1
opcache.preload=/app/warmup.php
opcache.preload_user=www-data   ; required when FPM starts as root
```

| per request, warm `.phel/cache` | first request | later requests |
|---|---|---|
| no opcache | 71ms | 69ms |
| opcache | 127ms | 16ms |
| opcache + `opcache.preload=warmup.php` | 19ms | 16ms |

Preload runs the load, not just the compile, because that links the Phel
runtime classes the compiled files extend. Preloading the files under
`.phel/cache/compiled/` with `opcache_compile_file()` instead prints a "Can't
preload unlinked class" warning for every compiled fn and still left the first
request at 57ms. Preloaded code stays until PHP restarts, so restart FPM on
every deploy. The per-request floor (about 16ms here) is the namespace
definitions running again: PHP resets them between requests, and only a worker
runtime keeps them.

Method: PHP 8.5.10 (Homebrew), Apple M4 Pro, phel-lang `main` at 975d30d0a,
the machine running other work (load average about 10 on 14 cores). CLI rows
time `hrtime()` inside the script and the whole `php` process around it,
median of 15 runs (3 for the cold and first-run rows). Per-request rows use
`php -S` as the FPM stand-in (same per-request reset, one worker): 5 server
starts of 20 requests each, the first-request median over the 5 starts, the
rest over the other 95 requests. A second full run agreed within 15%.

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

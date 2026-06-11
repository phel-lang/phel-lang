# Performance Tips

Make `phel test`, `phel run`, and other CLI commands fast. Applies to source-checkout use; PHAR users get the same benefits.

## TL;DR

```ini
; /your/php/conf.d/ext-opcache.ini
opcache.enable_cli=1
opcache.file_cache=/tmp/php-opcache
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.interned_strings_buffer=16
```

Create the directory once (`mkdir -p /tmp/php-opcache`) and restart your shell. Repeat runs of `./vendor/bin/phel test` then drop from ~12 s to sub-second on a warm cache.

## Why it is slow without opcache

Every `./vendor/bin/phel` invocation is a fresh PHP process. Without CLI opcache, PHP re-parses every `.php` file each run (`vendor/`, Phel compiler, Symfony console, project classes). `opcache.file_cache` persists compiled bytecode across processes.

Phel adds a compiled-code cache under `.phel/cache/` that memoizes Phel-to-PHP compilation per source hash (the cache flags and their semantics live in [configuration.md](configuration.md#caching)). The two complement each other: Phel's cache skips recompilation; opcache skips re-parsing the resulting PHP.

Invalidation is automatic, and changing the optimization level forces a full recompile. Each run hashes the `.phel` source (`md5`) against the stored entry; on mismatch it recompiles the file and its transitive dependents. Fresh compiles also `opcache_compile_file()` the generated PHP. Use the [reset steps](#cache-reset) only if something gets wedged.

For hot numeric/string fns, add `:tag` annotations on params and the return slot (`^int`, `^float`, `^string`, `^bool`). The compiler emits matching PHP type declarations and infers the return type from primitive ops in tail position, letting the tracing JIT specialize the call. Tag mismatches surface as Phel diagnostics at compile time. Measure with `composer bench-jit-baseline` and `composer bench-jit-tracing` (see [`internals/benchmarks.md`](internals/benchmarks.md)).

## Optimization levels

`phel-config.php` can opt the compiler into higher optimization levels (default 0):

```php
return new PhelConfig()
    ->withOptimizationLevel(2);
```

| Level | Effect |
|---|---|
| 0 | Off (default); output is byte-identical to previous releases |
| 1 | Reserved for auto-inlining single-expression private `defn-` (not implemented yet) |
| 2 | `^:pure` call-site inlining + rewrite of self-recursive tail calls into an implicit loop |

The level applies to `phel build`, `phel run`, `phel test`, `phel eval`, and `phel compile`. The REPL and nREPL always compile at level 0 so interactive redefinition stays predictable. `phel build -O2` (long form `--optimization-level=2`) overrides the configured level for a single build.

Level 2 trade-offs:

- `^:pure` is the author's promise that a single-arity `defn` is side-effect free and safe to inline at call sites; the compiler trusts the annotation instead of verifying it.
- Tail-call rewriting eliminates per-iteration PHP stack frames (deep self-recursion no longer overflows) at the cost of a shorter stack trace inside the loop.
- Changing the level automatically invalidates the compiled-code cache and the incremental `phel build` output, so the next run recompiles everything once.

## Memory limit

`./vendor/bin/phel` raises `memory_limit` to `-1` automatically. If you invoke PHP directly (`php ./vendor/bin/phel ...`) or embed Phel, bump the limit yourself: the compiler's `token_get_all` validation can exceed 128M on large projects.

```bash
php -d memory_limit=-1 ./vendor/bin/phel test
```

## Finding your php.ini

```bash
php --ini
```

Look for `Loaded Configuration File` and `Additional .ini files parsed`. On Homebrew (macOS) the opcache settings live in `/opt/homebrew/etc/php/<version>/conf.d/ext-opcache.ini`.

## Verifying opcache is active

```bash
php -r 'var_dump(opcache_get_status(false) !== false);'
```

Prints `bool(true)` when CLI opcache is on.

## Cache reset

If a run behaves oddly (stale compiled code, missing definitions, cache-hit crashes), wipe both caches and retry. The next invocation repopulates cleanly.

```bash
rm -rf .phel/cache
rm -rf /tmp/php-opcache
```

## Benchmarks (reference)

Measured locally on `tests/phel/core/var-quote.phel`:

| State | Time |
|---|---|
| Cold cache, no opcache | ~12 s |
| Cold cache, opcache on | ~2.6 s |
| Warm cache, opcache on | ~0.28 s |

Full suite (4 440 tests) dropped from ~76 s to ~61 s after enabling opcache CLI. Numbers vary by hardware; treat as ratio, not absolute.

## Related

- [`docs/internals/benchmarks.md`](internals/benchmarks.md): PHPBench suite for tracking regressions in the Phel compiler itself
- [Profile Guide](profile-guide.md): instrument a script to find hot fns
- PHP manual: [opcache configuration](https://www.php.net/manual/en/opcache.configuration.php)

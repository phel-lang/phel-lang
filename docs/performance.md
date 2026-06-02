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

Create the directory once: `mkdir -p /tmp/php-opcache`. Restart your shell. Repeat runs of `./vendor/bin/phel test` drop from ~12 s to sub-second on warm cache.

## Why it is slow without opcache

Every `./vendor/bin/phel` invocation is a fresh PHP process. Without CLI opcache, PHP re-parses every `.php` file each run (`vendor/`, Phel compiler, Symfony console, project classes). `opcache.file_cache` persists compiled bytecode across processes.

Phel adds a compiled-code cache under `.phel/cache/` memoizing Phel-to-PHP compilation per source hash. The two complement each other: Phel's cache skips recompilation; opcache skips re-parsing the resulting PHP.

Invalidation is automatic: each run hashes the `.phel` source (`md5`) against the stored entry, and on mismatch recompiles the file and its transitive dependents. Fresh compiles also `opcache_compile_file()` the generated PHP. Use the reset steps below only if something gets wedged.

For hot numeric/string fns, add `:tag` annotations on params and the return slot (`^int`, `^float`, `^string`, `^bool`). The compiler emits matching PHP type declarations and infers the return type from primitive ops in tail position, letting the tracing JIT specialize the call. Tag mismatches surface as Phel diagnostics at compile time. Measure with `composer bench-jit-baseline` and `composer bench-jit-tracing` (see [`internals/benchmarks.md`](internals/benchmarks.md)).

## Memory limit

`./vendor/bin/phel` raises `memory_limit` to `-1` automatically. If you invoke PHP directly (`php ./vendor/bin/phel ...`) or embed Phel, bump the limit yourself. The compiler's `token_get_all` validation can exceed 128M on large projects.

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

If a run behaves oddly (stale compiled code, missing definitions, cache-hit crashes), wipe both caches and retry:

```bash
rm -rf .phel/cache
rm -rf /tmp/php-opcache
```

Next invocation repopulates cleanly.

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

# Performance Tips

Guidance for making `phel test`, `phel run`, and other CLI commands fast on your machine. Applies to source-checkout use; PHAR users get the same benefits.

## TL;DR

```ini
; /your/php/conf.d/ext-opcache.ini
opcache.enable_cli=1
opcache.file_cache=/tmp/php-opcache
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.interned_strings_buffer=16
```

Create the directory once: `mkdir -p /tmp/php-opcache`. Restart your shell. Repeat runs of `bin/phel test` drop from ~12 s to sub-second on warm cache.

## Why it is slow without opcache

Every `bin/phel` invocation is a fresh PHP process. Without CLI opcache, PHP re-parses every `.php` file on every run: `vendor/`, Phel compiler, Symfony console, the project's own classes. With `opcache.file_cache` enabled, compiled bytecode persists across processes.

Phel also maintains a compiled-code cache of its own (stored under `sys_get_temp_dir() . '/phel'` by default) that memoizes Phel-to-PHP compilation per source hash. The two caches are complementary: Phel's cache skips recompilation; opcache skips re-parsing the resulting PHP.

Cache invalidation is automatic. Each run hashes the `.phel` source content (`md5`) and compares against the stored entry; on mismatch, the file is recompiled and transitive dependents are invalidated. No manual clear needed after editing a `.phel` file. Fresh compiles also call `opcache_compile_file()` on the generated PHP. Use the reset steps below only if something gets wedged.

## Memory limit

`bin/phel` raises `memory_limit` to `-1` automatically. If you invoke PHP directly (`php bin/phel ...`) or embed Phel in another tool, bump the limit yourself; the compiler's `token_get_all` validation can exceed 128M on sizable projects.

```bash
php -d memory_limit=-1 bin/phel test
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

If a run behaves oddly (stale compiled code, missing definitions, cache-hit crashes), nuke both caches and retry:

```bash
rm -rf "$(php -r 'echo sys_get_temp_dir();')/phel"
rm -rf /tmp/php-opcache
```

Next invocation repopulates cleanly.

## Benchmarks (reference)

Measured locally on 6-test file `tests/phel/test/core/var-quote.phel`:

| State | Time |
|---|---|
| Cold cache, no opcache | ~12 s |
| Cold cache, opcache on | ~2.6 s |
| Warm cache, opcache on | ~0.28 s |

Full suite (4 440 tests) dropped from ~76 s to ~61 s after enabling opcache CLI. Numbers vary by hardware; treat as ratio, not absolute.

## Related

- [`docs/internals/benchmarks.md`](internals/benchmarks.md) — PHPBench suite for tracking regressions in the Phel compiler itself
- PHP manual: [opcache configuration](https://www.php.net/manual/en/opcache.configuration.php)

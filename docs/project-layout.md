# Project Layout

Phel writes per-project runtime state under a single `.phel/` directory at the
project root. It is created lazily on first use and auto-ignored by Git via a
self-seeded `.phel/.gitignore` (`*`).

| Path                            | Owner            | Purpose                              |
| ------------------------------- | ---------------- | ------------------------------------ |
| `.phel/cache/`                  | Build / compiler | Namespace + compiled-code cache      |
| `.phel/lint-cache/index.json`   | Lint             | Per-file diagnostic cache            |
| `.phel/last-failed.txt`         | Test runner      | Backing file for `phel test --last-failed` |
| `.phel/repl-history`            | REPL             | Readline history |
| `.phel/error.log`               | Runtime          | Error log (was `/tmp/phel-error.log`) |
| `out/`                          | Build            | Compiled PHP entry points (build artifacts; lifecycle differs) |

- `out/`: gitignore in source repos; commit only when shipping the compiled PHP.

## Overrides

- **`$config->withPhelDir('/var/cache/phel')`** in `phel-config.php`: relocates the whole `.phel/` (cache, REPL history, last-failed, error log) out of the project root. Useful for WordPress plugins, shared hosting, or any web-accessible layout.
- **`PHEL_DIR` env var**: same effect at runtime; wins over `withPhelDir()`.
- **`PhelConfig::withCacheDir($path)`**: narrower override for just the build cache.
- **`PHEL_CACHE_DIR` env var**: final cache override; wins over `PHEL_DIR` and `withCacheDir()`. Useful for CI / Nix builds.

For the full list of `phel-config.php` options, caching flags, and precedence, see [Configuration](https://phel-lang.org/documentation/configuration/).

## Read-only filesystems

Directory creation is best-effort. On a read-only filesystem (Lambda, Docker
`:ro` mounts, sandbox runners), Phel skips `.phel/` creation silently and runs
without cache, last-failed tracking, or REPL history persistence. Set
`PHEL_CACHE_DIR=/some/writable/path` (e.g. `/tmp/phel-cache`) to relocate the
build cache when you still want caching.

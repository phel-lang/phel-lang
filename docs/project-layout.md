# Project Layout

Phel writes per-project runtime state under a single `.phel/` directory at the
project root. The directory is created lazily on first use and is auto-ignored
by Git via a self-seeded `.phel/.gitignore` (`*`).

| Path                            | Owner            | Purpose                              |
| ------------------------------- | ---------------- | ------------------------------------ |
| `.phel/cache/`                  | Build / compiler | Namespace + compiled-code cache      |
| `.phel/lint-cache/index.json`   | Lint             | Per-file diagnostic cache            |
| `.phel/last-failed.txt`         | Test runner      | Backing file for `phel test --last-failed` |
| `.phel/repl-history`            | REPL             | Readline history (was `.phel-repl-history`) |
| `.phel/error.log`               | Runtime          | Error log (was `/tmp/phel-error.log`) |
| `out/`                          | Build            | Compiled PHP entry points (build artifacts; lifecycle differs) |

- `out/`: gitignore in source repos; commit only when shipping the compiled PHP.

## Overrides

- **`$config->withPhelDir('/var/cache/phel')`** in `phel-config.php`: relocates the whole `.phel/` (cache, REPL history, last-failed, error log) out of the project root. Useful for WordPress plugins, shared hosting, or any web-accessible layout.
- **`PHEL_DIR` env var**: same effect at runtime; wins over `withPhelDir()`.
- **`PhelConfig::withCacheDir($path)`**: narrower override for just the build cache.
- **`PHEL_CACHE_DIR` env var**: final cache override; wins over `PHEL_DIR` and `withCacheDir()`. Useful for CI / Nix builds.
- **`PHEL_QUIET_MIGRATION=1`**: silences the stderr notice when legacy `.phel-repl-history` migrates into `.phel/repl-history`.

## Migration

Existing projects with a top-level `.phel-repl-history` get the file renamed
into `.phel/repl-history` automatically the next time the REPL boots. No
action required; the legacy filename will be removed in a future release.

## Read-only filesystems

Directory creation is best-effort. On a read-only filesystem (Lambda, Docker
`:ro` mounts, sandbox runners), Phel skips `.phel/` creation silently and
runs without cache, last-failed tracking, or REPL history persistence. Set
`PHEL_CACHE_DIR=/some/writable/path` (e.g. `/tmp/phel-cache`) to relocate the
build cache when you still want caching.

# ADR 0009: Write the standard library in Phel, ship it precompiled

- **Status**: Accepted (recorded retroactively; precompilation landed in #2443)
- **Date**: 2026-07-29

## Context

`phel.core` and its siblings could have been PHP functions exposed to the
language. Writing them in Phel makes the standard library the compiler's largest
consumer, which is the only realistic way to find out whether the language is
pleasant before users do. An awkward macro shows up in `src/phel/core/` first.

The cost lands at startup. `phel.core` is always loaded, and compiling it on every
invocation put ~1.2s in front of every `run`, `test` and `eval` from a PHAR, where
there is no writable cache to amortise into.

## Decision

The standard library is written in Phel, and the PHAR ships it **precompiled**.

- `build/build-phar.php` compiles the bundled namespaces and writes each `.php`
  next to its `.phel` source inside the archive, keeping path structure
  (`core/meta.phel` beside `core/meta.php`).
- `Build\Application\FileEvaluator` takes a precompiled-sibling fast path: a
  matching sibling is loaded instead of recompiling the source.
- The primary is loaded with `require_once`, so reloading an already-loaded
  bundled namespace cannot reset its forward-declared definitions (`map`, `seq`,
  `nil?`) to null (#2673).
- Build mode must persist across a build's whole `(load …)` chain, or a namespace
  loaded partway through compiles under the wrong mode.

Cold-start `run`, `test` and `eval` from the PHAR: ~1.2s to ~0.2s (#2443).

## Consequences

Two load paths for a bundled namespace, which must not diverge. A stale or
mismatched sibling produces behaviour that differs from a fresh compile and looks
like a compiler bug rather than a packaging one. Hence the PHAR being smoke-tested
in CI, not only built.

A stale local `build/out/phel.phar` makes `composer test` fail for reasons
unrelated to the working tree. Rebuild with `./build/phar.sh` before believing it.

A stdlib in Phel means compiler changes can break it in ways unit tests miss,
which is why `composer test-core` is part of the default gate.

Byte-stability of precompiled output is not promised: gensym counters are
process-global, so a build mixing fresh compiles with cache hits can renumber
generated names. Only behaviour is pinned.

## Enforcement

- `smoke.yml` builds the PHAR and runs `PharExecutionTest` against it
- `composer test-core` runs the Phel-level suites through `bin/phel test`
- `composer test-agents` runs the three bundled example apps against current source

## Alternatives considered

- **A PHP standard library exposed to Phel.** Removes the best feedback source on
  the language, and leaves `phel.core` unable to use macros.
- **Compiling at install time into vendor.** Does not help a PHAR, the mode with
  the worst cold start and no writable location.
- **Shipping compiled `.php` only.** The sources are what `phel doc`,
  jump-to-definition and the REPL read.

## See also

- [`build/README.md`](../../build/README.md),
  [ADR 0002](0002-compile-to-php-source.md),
  [Architecture](../internals/architecture.md)

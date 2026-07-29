# ADR 0009: Write the standard library in Phel, ship it precompiled

- **Status**: Accepted (recorded retroactively; precompilation landed in #2443)
- **Date**: 2026-07-29

## Context

`phel.core` and its siblings (`string`, `html`, `http`, `json`, `test`, `repl`,
`walk`, `pprint`, `reflect`, `mock`) could have been PHP functions exposed to the
language. Writing them in Phel instead makes the standard library the largest
consumer of the compiler, which is the only realistic way to find out whether the
language is pleasant before users do. A macro that is awkward to write shows up in
`src/phel/core/` first.

The cost lands at startup. `phel.core` is always loaded, and compiling it on every
invocation put roughly 1.2 seconds in front of every `run`, `test` and `eval` from
a PHAR, where there is no writable cache directory to amortise it into. For a CLI
that is the difference between a tool people reach for and one they avoid.

## Decision

The standard library is written in Phel, and the distributed PHAR ships it
**precompiled**.

- `build/build-phar.php` compiles the bundled namespaces and writes each resulting
  `.php` next to its `.phel` source inside the archive, keeping the path structure
  (`core/meta.phel` beside `core/meta.php`).
- `Build\Application\FileEvaluator` takes a precompiled-sibling fast path: when the
  sibling exists and matches, it is loaded instead of the source being recompiled.
- The primary is loaded with `require_once`, so a second load of an
  already-loaded bundled namespace cannot reset its forward-declared definitions
  (`map`, `seq`, `nil?`) to null (#2673).
- Build mode has to persist across a build's whole `(load …)` chain, otherwise a
  namespace loaded partway through is compiled under the wrong mode.

Cold-start `run`, `test` and `eval` from the PHAR went from about 1.2s to about
0.2s (#2443).

## Consequences

There are now two ways a bundled namespace can be loaded, and they must not
diverge. The failure mode is subtle: a stale or mismatched sibling produces
behaviour that differs from a fresh compile of the same source, and it looks like
a compiler bug rather than a packaging one. This is why the PHAR is smoke-tested
in CI rather than only built there, and why `PharExecutionTest` runs against the
built artifact in `smoke.yml`.

A stale local `build/out/phel.phar` makes `composer test` fail for reasons that
have nothing to do with the working tree. Rebuild with `./build/phar.sh` before
believing such a failure.

Writing the stdlib in Phel also means compiler changes can break it in ways unit
tests miss, which is what `composer test-core` exists for, and it is why the core
suite is part of the default `composer test` gate rather than an optional extra.

Byte-stability of the precompiled output is not promised. Gensym counters are
process-global, so a build mixing fresh compiles with cache hits can renumber
generated names. Only behaviour is pinned.

## Enforcement

- `smoke.yml` builds the PHAR and runs `PharExecutionTest` against it
- `composer test-core` runs the Phel-level suites through `bin/phel test`
- `composer test-agents` runs the three bundled example apps against current
  source, so a stdlib regression shows up as a red build on the pull request

## Alternatives considered

- **A PHP standard library exposed to Phel.** Rejected: it removes the project's
  best source of feedback on the language, and it would leave `phel.core`
  unable to use macros.
- **Compiling at install time into the vendor directory.** Does not help a PHAR,
  which is the distribution mode with the worst cold start and no writable
  location.
- **Shipping only compiled `.php` without the `.phel` sources.** Rejected: the
  sources are what `phel doc`, jump-to-definition and the REPL's source display
  read.

## See also

- [`build/README.md`](../../build/README.md)
- [ADR 0002](0002-compile-to-php-source.md)
- [Architecture](../internals/architecture.md)

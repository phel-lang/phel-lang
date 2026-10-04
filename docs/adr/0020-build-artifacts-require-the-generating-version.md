# ADR 0020: Build artifacts require the generating Phel version

- **Status**: Accepted
- **Date**: 2026-10-05
- **Amends**: 0005 (compatibility of generated artifacts)

## Context

Generated PHP calls compiler internals, runtime types and export plumbing that
are outside the public PHP API. Freezing those calls would constrain compiler
changes in every `1.x` release. The stability policy previously implied that
older artifacts were supported, although no test enforced that implication.
An artifact built by a newer global PHAR can already fail against an older
project runtime (#3519).

## Decision

Build output and exported PHP wrappers require exactly the Phel version that
generated them, including the patch version and development commit suffix. Their preambles carry that version.
Generated entry points check it through public `\Phel::assertBuiltWith()` after
autoloading and before bootstrap or program execution. Wrappers check it before
declaring their generated class.

Users rebuild with `phel build`, or regenerate wrappers with `phel export`, after
changing Phel. Source compatibility and the public PHP embedding API retain
their existing promises. Generated references to internal classes carry no
cross-version compatibility promise.

## Consequences

A runtime mismatch reports both versions and rebuild advice before the program
runs. The build command warns when the project's Composer lock names a different
Phel version, which catches the global PHAR case before deployment.

Compiled namespace files carry a comment stamp because PHP requires a namespace
declaration before executable statements. Their source-map offsets stay intact.
The source loader ignores stale or unstamped PHP siblings and recompiles their
Phel source. Hosts requiring generated namespace files directly must enforce the
rebuild rule themselves.
Artifacts generated before the guard existed also require rebuilding.

## Enforcement

`BuildCommandEntryPointE2ETest` builds a project, changes its entry-point stamp,
and verifies rejection before program output. `ExportCommandTest` verifies stale
wrapper rejection. The public PHP API snapshot pins `assertBuiltWith()`.

## Alternatives considered

- Freeze every emitted reference: makes compiler internals a permanent embedding
  surface without a source compatibility benefit.
- Accept a compatible semver range: a patch can change emitted internal calls,
  so a range cannot establish artifact compatibility.

## See also

[Stability policy](../stability.md) ·
[ADR 0005](0005-public-php-api-by-rule-and-snapshot.md) ·
`.agnostic-ai/rules/module-build.md`

# ADR 0010: Static analysis covers `src/` only

- **Status**: Accepted (recorded retroactively)
- **Date**: 2026-07-29

## Context

`src/php/` is analysed at PHPStan level 9 (max) and Psalm level 1, one config, no
relaxed second profile, and the whole tree clears it.

`tests/php/` is not analysed at all. That reads as an omission, and "just add
`tests/` to `paths`" comes back regularly.

It was tried: roughly 850 level-9 errors, no real defect. The errors are
structural. Mocks are `mixed` by construction, fixture arrays are deliberately
loose, and many tests exist to feed the compiler input that is wrong on purpose,
so "this value might not be a `Symbol`" is the assertion, not the bug.

## Decision

`phpstan.neon` and `psalm.xml` analyse `src/` only. Test code is out of scope and
is not expected to satisfy level 9.

Test quality is held by other instruments: the tests passing (`composer test`), a
ratcheted coverage floor (`coverage.yml`, nightly), mutation testing over `Lang/`
and the analyzer (`mutation.yml`, weekly), and `tests/php/Unit/Architecture/`
including `TestSuiteDependencyTest`.

## Consequences

Test code can use `mixed`, partial doubles and hand-built arrays without
suppressions, which keeps a focused regression test cheap to write. That matters
because the compiler's bug class is "this input produces the wrong output", fixed
by another fixture rather than a better-typed mock.

Cost: a typing mistake inside a test is caught by the test failing, not by
analysis. Tests run on every push, unlike the production paths analysis exists to
reach.

Related trap on the same boundary: the local PHPStan result cache can hide
`missingType.generics` errors that CI reports, so a newly typed method passes
locally and fails in CI. Clear the result cache when adding generic annotations.

Anybody proposing to widen the scope should bring a way to keep the noise out, not
a green run after 850 suppressions.

## Enforcement

- `phpstan.neon`: `paths: [%currentWorkingDirectory%/src/]`
- `psalm.xml`: `<projectFiles><directory name="src" /></projectFiles>`
- `quality.yml` runs both on every push
- `coverage.yml` and `mutation.yml` hold the test-quality half, ratcheted and never
  lowered to make a red build green

## Alternatives considered

- **`tests/` at level 9.** 850 errors, no defects, a permanent suppression list.
- **`tests/` at a lower level in a second config.** Two profiles to keep passing
  and a standing argument about which file belongs where, for almost no signal.
- **`tests/` excluding mocks and fixtures.** The exclusion list approximates the
  suite.

## See also

- [Stability policy](../stability.md#quality-gates-behind-the-promises)
- [Testing performance](../internals/testing-performance.md)
- `.agnostic-ai/rules/php.md`

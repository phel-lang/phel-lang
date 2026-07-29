# ADR 0010: Static analysis covers `src/` only

- **Status**: Accepted (recorded retroactively)
- **Date**: 2026-07-29

## Context

`src/php/` is analysed at PHPStan level 9 (max) and Psalm level 1, with a single
`phpstan.neon` and no relaxed second profile. That is a deliberate and expensive
position, and it holds: the whole of `src/` clears it.

`tests/php/` is not analysed at all. This looks like an omission every time
somebody new reads the config, and the proposal to "just add `tests/` to `paths`"
comes back regularly.

It was tried. A trial expansion reported roughly 850 level-9 errors and surfaced no
real defect. The errors were structural rather than incidental: mocks are `mixed`
by construction, fixture arrays are deliberately loose, and a large share of the
tests exist to feed the compiler input that is wrong on purpose, so "this value
might not be a `Symbol`" is the assertion rather than the bug.

## Decision

`phpstan.neon` and `psalm.xml` analyse `src/` and nothing else. Test code is not
in scope and is not expected to satisfy level 9.

Quality of test code is held by different instruments:

- the tests must pass (`composer test`),
- coverage is gated at a floor and ratcheted (`coverage.yml`, nightly),
- mutation testing over `Lang/` and the analyzer catches tests that assert nothing
  (`mutation.yml`, weekly),
- `tests/php/Unit/Architecture/` holds the structural rules, including
  `TestSuiteDependencyTest`.

## Consequences

Test code can use `mixed`, partial doubles and hand-built arrays without a
suppression comment, which is what keeps writing a focused regression test cheap.
Cheap tests are the point: the compiler's bug class is "this input produces the
wrong output", and the fix for that is another fixture, not a better-typed mock.

The cost is that a genuine typing mistake inside a test is caught by the test
failing rather than by analysis. In practice a test with a type error fails
loudly, because it is executed on every run, unlike the production code paths
static analysis exists to reach.

A related trap belongs here because it is the same boundary seen from the other
side: the local PHPStan result cache can hide `missingType.generics` errors that
CI reports, so a newly typed method may pass locally and fail in CI. Clear the
result cache when adding generic annotations.

Anybody proposing to extend the scope should read this record first and come with
a way to keep the noise out, not just a green run after 850 suppressions.

## Enforcement

- `phpstan.neon`: `paths: [%currentWorkingDirectory%/src/]`
- `psalm.xml`: `<projectFiles><directory name="src" /></projectFiles>`
- `quality.yml` runs both on every push
- `coverage.yml` and `mutation.yml` carry the test-quality half, both ratcheted
  and never lowered to make a red build green

## Alternatives considered

- **Analyse `tests/` at level 9.** Rejected: 850 errors, no defects, and a
  permanent suppression list.
- **Analyse `tests/` at a lower level, in a second config.** Rejected: two
  profiles means two things to keep passing and a standing argument about which
  file belongs to which. The signal at a low level over test code is close to
  nothing.
- **Analyse `tests/` but exclude mocks and fixtures.** Rejected: the exclusion
  list would approximate the test suite.

## See also

- [Stability policy: quality gates](../stability.md#quality-gates-behind-the-promises)
- [Testing performance](../internals/testing-performance.md)
- `.claude/rules/php.md`

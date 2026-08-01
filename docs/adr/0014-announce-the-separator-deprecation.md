# ADR 0014: The `\` separator deprecation announces by default

- **Status**: Accepted
- **Date**: 2026-08-02

## Context

[ADR 0006](0006-one-opt-in-deprecation-channel.md) put every compiler
deprecation behind one opt-in switch, and listed "on by default" under
alternatives considered with a precondition attached:

> **On by default.** Revisit only with a way to scope notices to first-party
> source.

The `\` namespace separator is the last deprecated language surface
([#2827](https://github.com/phel-lang/phel-lang/issues/2827)) and the only one
with a real migration cost. Two facts make its opt-in notice a problem rather
than a preference.

The [deprecation policy](../stability.md#deprecation-policy-for-1x) removes a
deprecated form **only in a major**, and promises "a notice for at least one full
minor" first. A separator still shipping at `1.0.0` therefore ships for the whole
of `1.x`, and the notice that is supposed to precede its removal has never been
shown to anyone who did not ask for it. A promise of warning, kept only for users
who already knew to look, is not kept.

The reason the switch was opt-in has also expired for this detector. Announcing
by default was noisy because Phel's own sources used the separator everywhere;
after #2926 they do not. Measured on `main`: the whole core suite, 6597
assertions, emitted **two** notices, both the same symbol, from
`resources/repl/startup.phel`, which this change fixes.

## Decision

`BackslashSeparatorDeprecator` reports whether or not `--warn-deprecations` is
set. Every other detector stays opt-in, and ADR 0006's decision is otherwise
untouched: one channel, one owner of dedup, suppression, attribution and message
shape.

Two things make it affordable, and both are conditions of the decision rather
than side effects:

1. **First-party scoping**, the precondition ADR 0006 named.
   `DeprecationWarnings::isReportableSource()` now also excludes any path under a
   `vendor/` directory. A deprecation inside a dependency is its author's to fix,
   and reporting it is how a channel earns the global silencing that loses it
   permanently.
2. **The existing per-`(file, subject)` dedup**, so a file naming one
   `\`-separated symbol a hundred times reports once.

`DeprecationWarnings::announceOnceAtOrigin()` is the entry point, and it is
deliberately narrow: it is for a deprecation already scheduled for removal at the
next major. It is not a general escape from the opt-in rule.

## Consequences

- A project on the backslash form sees the notice from `1.0.0` without opting in,
  which is what makes a `2.0` removal defensible instead of abrupt.
- Phel's own integration fixtures pin the deprecated syntax on purpose, so the
  integration suite reports many notices where it used to report none. They are
  first-party and correct: those fixtures really do use the deprecated form.
- A project living under a directory literally named `vendor` is suppressed as
  though it were a dependency. That is the safe direction to be wrong in: a
  missing notice, never a misdirected one.
- Announcing does not shorten the schedule. `\` still works throughout `1.x`;
  #2827 owns the removal.

## Enforcement

- `BackslashSeparatorDeprecatorTest`: announces with the flag off, and stays
  silent for a `vendor/` path.
- `WarnDeprecationsFlagTest`: the separator is the documented exception; the
  test that pinned silence now pins the announcement.
- `DeprecationWarningsTest` covers `isReportableSource()`.

## Alternatives considered

- **Leave it opt-in and remove at 2.0 anyway.** Removes a form most users were
  never warned about, at the one release where the project asks to be trusted on
  stability.
- **Remove `\` at 1.0.** Same objection, sooner, and it contradicts what
  [the upgrade guide](../migration/upgrade-0.49-to-1.0.md) already promises.
- **Flip the whole channel on.** The scoping now exists, but the other
  deprecations are not scheduled for removal, so the noise buys nothing yet.

## See also

[ADR 0006](0006-one-opt-in-deprecation-channel.md) ·
[ADR 0008](0008-dot-namespace-separator.md) ·
[backslash-to-dot.md](../migration/backslash-to-dot.md) · #2827, #2926

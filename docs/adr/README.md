# Architecture Decision Records

An ADR records a decision that shaped the repository, the situation that forced
it, and what it costs. The code says *what* Phel does; `../internals/` says *how*;
these pages say **why it is not something else**.

They are written once and then left alone. An ADR is not documentation to be kept
up to date: when a decision changes, the old record stays as it was and a new ADR
supersedes it. That trail is the value. A page that is silently rewritten every
time the answer changes cannot tell you which arguments were already had.

## Index

| # | Title | Status |
|---|---|---|
| [0001](0001-record-architecture-decisions.md) | Record architecture decisions | Accepted |
| [0002](0002-compile-to-php-source.md) | Compile to PHP source, not a bytecode VM | Accepted |
| [0003](0003-modules-talk-through-facades.md) | Modules talk to each other through facades only | Accepted |
| [0004](0004-accept-four-module-cycles.md) | Accept four module cycles and pin them | Accepted |
| [0005](0005-public-php-api-by-rule-and-snapshot.md) | Define the public PHP API by rule, gate it by snapshot | Accepted |
| [0006](0006-one-opt-in-deprecation-channel.md) | One opt-in channel for compiler deprecations | Accepted |
| [0007](0007-clojure-style-interop-is-the-source-spelling.md) | Clojure-style interop is the source spelling | Accepted |
| [0008](0008-dot-namespace-separator.md) | The namespace separator is `.`, and `\` still parses | Accepted |
| [0009](0009-stdlib-in-phel-precompiled-in-the-phar.md) | Write the standard library in Phel, ship it precompiled | Accepted |
| [0010](0010-static-analysis-covers-src-only.md) | Static analysis covers `src/` only | Accepted |
| [0011](0011-persistent-collections-in-php.md) | Persistent collections implemented in PHP | Accepted |
| [0012](0012-non-hygienic-macros-with-auto-gensym.md) | Non-hygienic macros with auto-gensym | Accepted |

Statuses: **Proposed** (open for argument), **Accepted** (in force), **Superseded
by NNNN** (kept for the trail, no longer in force), **Deprecated** (in force but
being unwound, with no replacement decided yet).

## When to write one

Write an ADR when a choice satisfies all three:

1. Somebody will ask "why is it like this?" in a year, and the answer is not in
   the code.
2. Reversing it costs more than a pull request.
3. A reasonable contributor would otherwise "fix" it.

That last one is the practical test. Most of the records here exist because the
decision looks like an oversight from the outside: four module cycles that a
dependency test allows, a `php/*` form that is both deprecated and the compilation
target, static analysis that stops at `src/`. Each was argued, and each argument
was about to be repeated.

Do **not** write an ADR for a bug fix, a refactor that preserves behaviour, a
naming convention (those live in `.claude/rules/` and `AGENTS.md`), or anything a
test already explains at the point of failure.

## Writing one

```bash
cp docs/adr/template.md docs/adr/00NN-short-kebab-title.md
```

Take the next free number, keep the title a claim rather than a topic ("Compile to
PHP source" beats "Compilation strategy"), and add the row to the index above.
Ship it in the pull request that makes the decision, not afterwards.

Records numbered here before an ADR process existed are marked **recorded
retroactively**: the decision was made in the pull request or issue each one
cites, and this page is the first place the reasoning was written down as
something other than a review comment.

## Superseding

Never edit a decision out of an accepted ADR. Instead:

1. Write the new ADR, with a `Supersedes: NNNN` line.
2. Change the old one's status to `Superseded by NNNN` and add the link. That is
   the only edit an accepted ADR takes.
3. Update the index.

## See also

- [Stability policy](../stability.md): what a version number promises. The
  policy is normative; these records explain how it got its shape.
- [Specification](../spec/README.md): the frozen language surface.
- [Internals](../internals/README.md): how the implementation works today.
- `src/php/<Module>/CLAUDE.md`: per-module API and constraints.

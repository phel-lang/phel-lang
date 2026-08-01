# Architecture Decision Records

Why the repository is shaped this way. Code says what, `../internals/` says how.

Records are immutable: a changed decision gets a new ADR superseding the old one.

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
| [0013](0013-static-property-spelling.md) | A static property is `$prop` to read and a plain name to assign | Accepted |
| [0014](0014-announce-the-separator-deprecation.md) | The `\` separator deprecation announces by default | Accepted |

Statuses: **Proposed**, **Accepted**, **Superseded by NNNN**, **Deprecated** (in
force, being unwound).

## When to write one

All three must hold:

1. Somebody will ask "why is it like this?" in a year and the code does not say.
2. Reversing it costs more than a pull request.
3. A reasonable contributor would otherwise "fix" it.

Not an ADR: bug fixes, behaviour-preserving refactors, conventions (those live in
`.agnostic-ai/rules/`), anything a failing test explains.

## Writing one

`cp docs/adr/template.md docs/adr/00NN-short-kebab-title.md`. Next free number,
never reused. Title is a claim. Add the index row. Ship it in the pull request that
makes the decision.

Records marked **recorded retroactively** predate this process and cite the pull
request or issue where the argument happened.

## Superseding

New ADR carries `Supersedes: NNNN`. Old one gets `Superseded by NNNN` and a link,
the only edit an accepted ADR takes. Update the index.

## See also

[Stability policy](../stability.md) · [Spec](../spec/README.md) ·
[Internals](../internals/README.md) · `src/php/<Module>/CLAUDE.md`

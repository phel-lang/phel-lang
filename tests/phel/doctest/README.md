# Documentation doctests

`runner.phel` evaluates the `` ```phel `` code blocks in a curated set of guides
and asserts that every `; => <value>` annotation matches what the form actually
evaluates to. It is the regression lock for the copy-paste-first examples a
newcomer runs.

## Run

```bash
composer test-docs
# or
./bin/phel run tests/phel/doctest/runner.phel
```

Exits non-zero (and lists every mismatch as `expected:` / `got:`) when an example
drifts from the compiler's real output. Runs in CI as the **Documentation
doctests** job.

## How a block is checked

- The files in scope are the `doc-files` allowlist at the bottom of `runner.phel`.
- Each file is loaded into one fresh namespace; its blocks are split into
  top-level forms and evaluated once, in order, so `def`/atom setup in one block
  stays visible to later ones (like loading the file in a REPL).
- A form is asserted when its closing line, or the comment line right after it,
  carries `; => <value>`. Comparison is value-oriented: the lazy/seq `@` marker,
  optional commas, and list/lazyseq/vector brackets are normalized away, so it
  catches value drift, not REPL-only formatting.

## Writing doctestable examples

- Put the expected value inline: `(inc 41) ; => 42`. Trailing notes after the
  value are fine, as `; note` or `(note)` — they are stripped before comparison.
- An annotation that is not a literal value (prose, or an `...` placeholder) is
  ignored, so explanatory `;` comments are safe.

## Opting a block out

A block is skipped when it is not deterministic, pure-Phel source: it contains an
`(ns ...)` form, a `#php` literal, a REPL transcript prompt (`app:1>`), or the
explicit marker `;; doctest: skip`. Add `;; doctest: skip` to exclude a block
that is illustrative rather than runnable.

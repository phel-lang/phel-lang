# Upgrading from 0.49 to 1.0

`1.0.0` is a stability commitment, not a feature release: what arrives is a
promise that what exists stops moving. See
[the stability policy](../stability.md) for what it covers.

Most projects need nothing. Where there is work, it is removing calls to things
that have been printing deprecation notices for several releases.

## Step 1: find out whether you are affected

Warnings are off by default. Turn them on for one run:

```bash
vendor/bin/phel run --warn-deprecations src/main.phel
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel test
```

```php
return PhelConfig::forProject()->withWarnDeprecations(true);
```

Uses inside Phel's own stdlib are suppressed, so the output lists only code you
own. **A clean run on 0.49 means the upgrade is a version bump.** Notices go to
stderr and cannot break a build.

## Step 2: reader syntax

`#| |#`, bare `#` comments, `|(x)` short fns, `,` / `,@` unquote and `foo$`
auto-gensym are gone. Full table and replacements:
[removed-deprecated-core-fns.md](removed-deprecated-core-fns.md#reader-syntax-2827).

**`,` is the dangerous one.** Everything else stops parsing, so the compiler
finds it. `,` is now plain whitespace, so `` `(f ,x) `` still parses and quietly
*quotes* `x`. No error, only a wrong expansion.

```bash
grep -rnE ",[A-Za-z0-9_(\[{'\`~@:*+-]" --include='*.phel' src/ tests/
```

A comma followed by whitespace is fine and always was: `{:a 1, :b 2}` is
idiomatic. Do not restrict the sweep to `.phel` files: anything that *generates*
Phel (a PHP heredoc, a template, a scaffold) needs the same pass. Phel's own
repository had two such cases, both invisible to a `.phel` grep.

## Step 3: definitions and metadata

| Old | New |
|---|---|
| `(set-meta! v m)` | `(with-meta v m)`, or attach metadata at definition |
| `(phel.test/print-summary)` | `(phel.test/successful?)` plus your own reporting, or the default reporter |
| `^:reference` parameters | pass and return values; use an `atom` for shared mutable state |

`^:reference` emitted a PHP by-reference parameter, a PHP concept rather than a
Phel one, and interacted badly with the rest of the language. `php/ref` remains
for genuine interop with a function taking an output parameter.

## Step 4: CLI flags

| Old | New |
|---|---|
| `phel index --out <dir>` | `phel index --output <dir>` |
| `phel config --json` | `phel config --format=json` |

Both printed a one-line stderr notice on every run before removal.

## Step 5: the REPL history file

`.phel-repl-history` in the project root is no longer read or migrated. History
lives at `.phel/repl-history`. The old file is left where it is and ignored:

```bash
mkdir -p .phel && mv .phel-repl-history .phel/repl-history
```

## Step 6: if you embed Phel in PHP

Two changes for code calling Phel's PHP classes directly, both shipped in 0.49
and marked **BREAKING** in the changelog:

- The five transfers named by `ApiFacadeInterface` moved from `Phel\Api\Transfer\`
  to `Phel\Shared\Api\` (`Diagnostic`, `ProjectIndex`, `Definition`, `Location`,
  `Completion`). Shapes unchanged.
- The four unrelated `FileIoInterface` interfaces were renamed after what each
  does: `DirectoryWritabilityCheckerInterface`, `FileContentsIoInterface`,
  `ValidatedFileIoInterface`, `FileWriterInterface`.

From 1.0 on such changes need a major, and everything outside the
[public surface](../stability.md#public-php-api) carries `@internal`, so an IDE
and a static analyser will tell you when you reach for one. Depending on an
internal class is worth
[an issue](https://github.com/phel-lang/phel-lang/issues): it usually means a
facade is missing a method.

## What is *not* changing

- **The `\` namespace separator still works.** Deprecated in favour of `.`, not
  removed in 1.0 ([#1567](https://github.com/phel-lang/phel-lang/issues/1567)).
  Details in [backslash-to-dot.md](backslash-to-dot.md).
- **Every other public `phel.*` function.** Pinned in
  `tests/php/Integration/Api/core-api.snapshot.txt`; a test fails if a definition
  or arity disappears.
- **`phel-config.php`** keys and its `with*()` builder.
- **The `.phel/` directory layout.**

## After upgrading

Run `phel doctor`, then your own suite. If something behaves differently from
Clojure, check [the divergence catalogue](../spec/clojure-divergences.md) first:
everything listed there is deliberate.

## See also

[Stability policy](../stability.md) ·
[Language surface spec](../spec/language-surface.md) ·
[Currently deprecated](deprecated-surface.md) ·
[Removed](removed-deprecated-core-fns.md)

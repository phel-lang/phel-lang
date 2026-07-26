# Upgrading from 0.49 to 1.0

`1.0.0` is a stability commitment, not a feature release. Almost nothing new
arrives with it; what arrives is a promise that what already exists stops moving.
See [the stability policy](../stability.md) for exactly what that promise covers.

The upgrade is therefore small, and most projects need nothing at all. The work,
where there is any, is removing calls to things that have been printing
deprecation notices for several releases.

## Step 1: find out whether you are affected

Deprecation warnings are off by default. Turn them on for one run:

```bash
vendor/bin/phel run --warn-deprecations src/main.phel
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel test
```

or, project-wide:

```php
return PhelConfig::forProject()->withWarnDeprecations(true);
```

Uses inside Phel's own bundled standard library are suppressed, so the output
lists only code you own. **A clean run on 0.49 means the upgrade is a version
bump.** Everything below is a fix for something that run reported.

Deprecation notices go to stderr and never corrupt program output; a notice
cannot break a build.

## Step 2: reader syntax

These parse today and are removed in 1.0.

| Old | New |
|---|---|
| `#\| block comment \|#` | `;` line comments, or `#_` to discard a form |
| `# bare comment` | `;` (or `;;` for a whole-line comment) |
| `\|(x)` short function | `#(x)` |
| `` `(f ,x) `` | `` `(f ~x) `` |
| `` `(f ,@xs) `` | `` `(f ~@xs) `` |
| `foo$` auto-gensym | `foo#` |

**`,` is the dangerous one.** Every other item above stops parsing, so the
compiler finds it for you. `,` is now plain whitespace, which means
`` `(f ,x) `` still parses and quietly *quotes* `x` instead of unquoting it. There
is no error, only a wrong expansion.

Find it with:

```bash
grep -rnE ",[A-Za-z0-9_(\[{'\`~@:*+-]" --include='*.phel' src/ tests/
```

A comma followed by whitespace is fine and always was: `{:a 1, :b 2}` is
idiomatic and `phel format` preserves it.

Do not restrict the sweep to your `.phel` files. Anything that *generates* Phel
(a PHP heredoc, a template, a scaffold) needs the same pass. Phel's own repository
had two such cases and both were invisible to a `.phel` grep.

## Step 3: definitions and metadata

| Old | New |
|---|---|
| `(set-meta! v m)` | `(with-meta v m)`, or attach the metadata at definition |
| `(phel\test/print-summary)` | `(phel\test/successful?)` plus your own reporting, or the default reporter |
| `^:reference` parameters | pass and return values; use an `atom` where you need shared mutable state |

`^:reference` deserves a note: it emitted a PHP by-reference parameter, which is
a PHP concept rather than a Phel one, and it interacted badly with the rest of the
language. `php/ref` remains for genuine interop with a PHP function that takes an
output parameter.

## Step 4: CLI flags

| Old | New |
|---|---|
| `phel index --out <dir>` | `phel index --output <dir>` |
| `phel config --json` | `phel config --format=json` |

The old spellings printed a one-line notice on stderr on every run.

## Step 5: the REPL history file

`.phel-repl-history` in the project root is no longer read or migrated. History
lives at `.phel/repl-history`. Nothing is lost: the old file is left where it is
and simply ignored. Move it if you want the history back:

```bash
mkdir -p .phel && mv .phel-repl-history .phel/repl-history
```

## Step 6: if you embed Phel in PHP

Two things changed for code that calls Phel's PHP classes directly, both already
shipped in 0.49 and both marked **BREAKING** in the changelog:

- The five transfers named by `ApiFacadeInterface` moved from `Phel\Api\Transfer\`
  to `Phel\Shared\Api\`: `Diagnostic`, `ProjectIndex`, `Definition`, `Location`,
  `Completion`. The class shapes did not change.
- The four unrelated interfaces all named `FileIoInterface` were renamed after
  what each does: `DirectoryWritabilityCheckerInterface`, `FileContentsIoInterface`,
  `ValidatedFileIoInterface`, `FileWriterInterface`.

From 1.0 on, changes like these can only happen in a major, and every class in
`src/php/` outside the [public surface](../stability.md#public-php-api) carries
`@internal`, so your IDE and static analyser will tell you when you reach for one.

If you find yourself depending on an internal class, that is worth
[an issue](https://github.com/phel-lang/phel-lang/issues): it usually means a
facade is missing a method.

## What is *not* changing

- **The `\` namespace separator still works.** `(ns my-app\core)` and
  `phel\string/join` keep parsing. It is deprecated in favour of `.` and has its
  own tracking issue ([#1567](https://github.com/phel-lang/phel-lang/issues/1567)),
  but it is not removed in 1.0. Migrate at your own pace;
  [backslash-to-dot.md](backslash-to-dot.md) has the details.
- **Every other public `phel.*` function.** The full list is pinned in
  `tests/php/Integration/Api/core-api.snapshot.txt` and a test fails if any
  definition or arity disappears.
- **`phel-config.php`.** Its keys and the `with*()` builder are frozen.
- **The `.phel/` directory layout.**

## After upgrading

Run `phel doctor` to confirm the environment, then your own suite. If something
behaves differently from Clojure and you are not sure whether it is a bug, check
[the divergence catalogue](../spec/clojure-divergences.md) first: everything listed
there is deliberate.

## See also

- [Stability policy](../stability.md): what 1.x promises, and for how long
- [Language surface spec](../spec/language-surface.md): the frozen language
- [The currently deprecated surface](deprecated-surface.md): what is still shipped and still deprecated
- [Removed deprecated core functions](removed-deprecated-core-fns.md): the full removal record

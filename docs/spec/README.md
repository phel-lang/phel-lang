# Phel Specification

The pages in this directory are **normative**. They describe what the language
promises, not how the compiler happens to work today. `../internals/` is the
opposite: descriptive, and free to change with the implementation.

| Page | Answers |
|---|---|
| [Language surface](language-surface.md) | Which reader syntax, special forms and standard-library definitions are frozen for 1.x |
| [Clojure divergences](clojure-divergences.md) | Where Phel deliberately behaves differently from Clojure, and why |

The PHP-side half of the same contract is [../stability.md](../stability.md): the
public embedding API, the deprecation policy, and the PHP and platform support
policies. The reasoning behind the shape of both, and behind the divergences
themselves, is recorded in [../adr/](../adr/README.md).

## Each page is checked, not just written

| Claim | Check |
|---|---|
| The special-form list is closed | `tests/php/Unit/Architecture/LanguageSurfaceSpecTest.php` parses the table and compares it to the analyzer's dispatch registry |
| The standard library keeps its definitions and arities | `tests/php/Integration/Api/CoreApiSurfaceTest.php` against `core-api.snapshot.txt` |
| The public PHP API keeps its signatures | `tests/php/Unit/Architecture/PublicApiSurfaceTest.php` against `public-api.snapshot.txt` |
| Internal classes are marked internal | `tests/php/Unit/Architecture/InternalAnnotationTest.php` |
| The Clojure divergences are the ones we meant | the `:phel` branches in [clojure-test-suite](https://github.com/phel-lang/clojure-test-suite), run nightly |

A specification nobody can fail is a wish. Each of the above fails a build.

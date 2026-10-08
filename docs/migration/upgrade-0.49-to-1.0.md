# Upgrading to 1.0

Applies to any `0.49` or later release; the filename names the oldest release it
covers. Each change below says which release made it, so from a later `0.x` you
can skip what you already have.

`1.0.0` is a stability commitment, not a feature release: what arrives is a
promise that what exists stops moving. See
[the stability policy](../stability.md) for what it covers.

The work comes in three kinds: deprecated surface that is now gone, code the
compiler now rejects, and code that compiles and behaves differently. Only the
first announced itself in advance.

## Step 0: the environment

| | 0.49 | 1.0 | Since |
|---|---|---|---|
| PHP | `>=8.4` | `>=8.5` | 0.53 |
| `symfony/console` | `^6.0\|^7.0\|^8.0` | `^7.3\|^8.0` | 0.50 |
| `gacela-project/gacela` | `^1.18` | `^2.6` | 0.50 |

Upgrade those first; Composer refuses the update otherwise. A Symfony 6.4 LTS
app that embeds Phel has to move to 7.3 or later.

## Step 1: find deprecated code

Turn deprecation warnings on for one run:

```bash
vendor/bin/phel run --warn-deprecations src/main.phel
PHEL_WARN_DEPRECATIONS=1 vendor/bin/phel test
```

```php
return PhelConfig::forProject()->withWarnDeprecations(true);
```

Uses inside Phel's own stdlib are suppressed, so the output lists only code you
own. Notices go to stderr and cannot break a build.

A clean run means no deprecated surface is left. It does not find the changes in
steps 3 and 4, which were never deprecated first: after upgrading, run your test
suite.

## Step 2: reader syntax

`#| |#`, bare `#` comments, `|(x)` short fns, `,` / `,@` unquote and `foo$`
auto-gensym are gone since 0.50. Full table and replacements:
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

## Step 3: code that no longer compiles

Each of these used to compile. The error names the file, line and code.

| Before | Error | Fix | Since |
|---|---|---|---|
| `(php/new DateTime "2024-01-01")`, `(php/-> o (m))`, `(php/:: C (m))` | `PHEL012` | `(new DateTime "2024-01-01")`, `(.m o)`, `(C/m)` ([details](deprecated-surface.md#redundant-interop-forms-phpnew-php--php)) | 0.52 |
| `(set-var *x* 3)` | `PHEL012` | `(alter-var-root (var *x*) (fn [_] 3))` | 0.52 |
| `(push [] 1)`, `put`, `unset`, `put-in`, `unset-in`, `values`, `function?`, `hash-map?`, `id`, `str-contains?` | `PHEL001` | [removed-deprecated-core-fns.md](removed-deprecated-core-fns.md) | 0.50 |
| `{:a 1 :a 2}`, `#{1 1}` | `PHEL203` Duplicate key | drop the repeated key; `{:a 1 :a 2}` used to read as `{:a 2}` | 0.54 |
| `(defn sq [n] (* n n)) (sq 3 4)` | `PHEL002` | pass the declared number of arguments; extra ones used to be ignored | 0.54 |
| `(if-not t a b c)`, `(if-let [x v] a b c)`, `(assert true "m" :x)`, a call to your own macro with more arguments than it declares | `PHEL002` | pass the declared number of arguments; a macro used to drop the extra ones | after 0.54 |
| `(declare f [x])` | `PHEL005`, `declare takes symbols` | drop the arglist: `declare` takes names only | after 0.54 |
| `(get {:a 1} :a nil :extra)`, `(max)`, `(min)` | `PHEL002` | core fns declare real arities since 0.50, so a wrong count is an arity error | 0.50 |
| `(:require app.util :refer [nope])` for a name `app.util` does not define, or a private one | `PHEL013` | refer only what the namespace defines publicly | 0.54 |
| `(:require phel.strng)`, a `phel.*` or `clojure.*` namespace Phel does not ship | `PHEL014` Cannot find namespace, with a did-you-mean | fix the name | 0.54 |
| `"\400"`, an octal escape above `\377` | compile error | it used to wrap silently: `"\400"` was NUL | 0.53 |
| `recur` inside a `foreach`, `doseq`, `dotimes` or `dofor` body, aimed at an enclosing `loop` or fn | `PHEL010` | write the `loop` inside the body, or use `reduce`. It used to keep iterating the inner loop | after 0.54 |
| `[~@a]`, `(println ~a)`: `~` or `~@` outside a syntax-quote | `PHEL210` | use these markers only inside `` ` ``; they used to read as whitespace, so `[~@a]` read as `[a]` | after 0.54 |
| `(defprotocol P (m [this]) (m [this x]))`, one method in two forms | `PHEL005`, `Function m in protocol P was redefined` | list every arity in one form: `(m [this] [this x])`; the second form used to replace the first | after 0.54 |
| A `defstruct` or `defrecord` method named like one every struct has, such as `find` or `merge` | `PHEL007` | rename the method; `find` used to replace the struct's own lookup, so `get` and `=` broke | after 0.54 |
| `(defstruct is [a])`, a type named `let` or `is`, an interface constant named `let`, `is` or `namespace` | compile error | choose another name; PHP 8.6 reserves them | after 0.54 |

## Step 4: code that compiles and behaves differently

| Change | Before | After | Since |
|---|---|---|---|
| `with-meta` returns a copy for every value | `(with-meta a {:x 1})` as a statement changed an atom, symbol, keyword or fn in place | it returns a new value; keep it, or use `reset-meta!` / `alter-meta!` on an atom or var ([ADR 0019](../adr/0019-with-meta-returns-a-copy.md)) | 0.53 |
| Namespaced keys in map destructuring | `{:my/keys [a]}` read `:a` | it reads `:my/a`, as `{:keys [my/a]}` does; write `{:keys [a]}` for `:a` | 0.54 |
| `transduce` calls the reducer's completion | a 2-arity reducer worked | `(transduce (map inc) (fn [acc x] (+ acc x)) 0 xs)` throws `PHEL401`; wrap the reducer in `completing` | 0.54 |
| `seq` and `next` keep a nil element | `(next (map identity [1 nil 3]))` was nil, so `(reduce + (map :price items))` stopped silently at a missing key | it is `(nil 3)`, so that `reduce` now throws on `(+ acc nil)`: filter or default the nils | 0.54 |
| `contains?` on a vector with a non-index key | `(contains? [1 2] :a)` was `true` | `false` | 0.54 |
| `partition-all` with a size or step that is not a positive int | size 0 returned an empty seq, step 0 never ended | it throws | 0.54 |
| `sort-by` calls the key fn once per element | twice per comparison | same order; an impure key fn runs far fewer times | 0.50 |
| Associative PHP arrays in `merge`, `merge-with`, `conj`; JSON objects in `phel.json/decode` | treated as lists | treated as maps; an empty JSON object decodes to `{}` | 0.51 |
| A `^int` return on the native arithmetic path | could become a BigInt | overflows to float, as PHP does | 0.50 |
| The `\` namespace separator announces itself | silent unless `--warn-deprecations` | one notice per file without the flag ([ADR 0014](../adr/0014-announce-the-separator-deprecation.md)); see [backslash-to-dot.md](backslash-to-dot.md) | 0.50 |
| Collection methods that return a copy are `#[NoDiscard]` | dropping `(.put m k v)` did nothing silently | PHP warns; keep the result, or cast to `(void)` when deliberate | 0.53 |
| Sequence functions walk a map, sorted map or struct as `[key value]` entries | `(take 1 {:a 1 :b 2})` was `(1)`, `(set {:a 1})` was `#{1}` | `([:a 1])`, `#{[:a 1]}`, as in Clojure and as `seq` and `into` already did. Covers `take`, `drop`, `filter`, `remove`, `keep`, `reduce` with an init, `last`, `rest`, `partition`, `distinct`, `frequencies`, `group-by` and the rest of the list in the changelog. Use `(vals m)` where you relied on the values | after 0.54 |
| `take`, `drop`, `take-last`, `drop-last`, `take-nth`, `split-at` with a count that is not a whole number | `(drop 2.5 xs)` dropped 2; a ratio threw | the count rounds up, as in Clojure: drops 3. Pass `(int n)` to keep the old count | after 0.54 |
| `partition` with a count that is not an integer, `2.0` included | returned chunks | returns `()`, as in Clojure | after 0.54 |
| `compare` and `sort` on lazy seqs, maps and sets | `(compare (range 5) (range 5))` was `1`; maps and sets had an arbitrary order | seqs compare element-wise, so it is `0`; two unequal maps or sets throw. Sort them with `sort-by` and a key fn | after 0.54 |
| `phel.http-client` URLs and redirects | `file://`, `php://` and `data://` URLs read local data; custom headers followed a redirect to another origin | only `http` and `https`; a cross-origin redirect keeps only standard headers | after 0.54 |

## Step 5: definitions and metadata

| Old | New |
|---|---|
| `(set-meta! v m)` | `(reset-meta! v m)` for an atom or var. For any other value use `(with-meta v m)` and keep the returned value (step 4). |
| `(phel.test/print-summary)` | `(phel.test/successful?)` plus your own reporting, or the default reporter |
| `^:reference` parameters | `^:by-ref`. `^:reference` now compiles silently by value, so search for the literal `:reference`. |

`php/ref` remains for genuine interop with a function taking an output
parameter.

## Step 6: CLI flags

| Old | New |
|---|---|
| `phel index --out <dir>` | `phel index --output <dir>` |
| `phel config --json` | `phel config --format=json` |

Both printed a one-line stderr notice on every run before removal in 0.50.

Scripts that check exit codes: a command that cannot run as asked (a missing
path, an unknown `--format`, `--reporter` or `-O` value, a missing `--config`
or `--ref` file, a Phel environment variable with a value it cannot read) exits
2 with one line on stderr and nothing on stdout. It used to exit 0 or 1
depending on the command. Exit 1 still means the command ran and found
something.

Environment variables: every switch reads `1/true/yes/on` as on and
`0/false/no/off` as off, and an empty value as not set. `PHEL_WARN_DEPRECATIONS=false`
and `PHEL_NO_OPCACHE_REEXEC=0` used to turn their switch on, and
`PHEL_OPCACHE_REEXEC=0` turned the restart on; each now does what it says. A
`PHEL_TEST_WORKERS` that is not a whole number of at least 1 used to be
ignored and now exits 2. The full list is in the
[CLI reference](../cli-reference.md#environment-variables).

Tools that match diagnostic codes: many analyzer errors that printed
`PHEL007` now carry their own code. A wrong number of arguments such as `(if)`
is `PHEL002`, a wrong kind of value such as `(fn 1)` is `PHEL003`, a bad
binding is `PHEL008`, and an unresolvable `catch` type, `var` target or `use`
class is `PHEL001`.

Tools that read diagnostic positions: `phel lint` (every format), `phel analyze`
and the `api-daemon` `analyzeSource` method give 1-based columns, as they
already did lines, so a column is one higher than before. The same holds for
`Phel\Shared\Api\Diagnostic` from `ApiFacade::analyzeSource()`. `phel analyze`
prints `uri` as an absolute path, and `phel lint --format=github` prints `file=`
relative to the working directory. The LSP still sends 0-based positions.

## Step 7: the REPL history file

`.phel-repl-history` in the project root is no longer read or migrated. History
lives at `.phel/repl-history`. The old file is left where it is and ignored:

```bash
mkdir -p .phel && mv .phel-repl-history .phel/repl-history
```

## Step 8: if you embed Phel in PHP

Changes for code calling Phel's PHP classes directly, each marked **BREAKING**
or **BC** in the changelog:

- In 0.50, the five transfers named by `ApiFacadeInterface` moved from
  `Phel\Api\Transfer\` to `Phel\Shared\Api\` (`Diagnostic`, `ProjectIndex`,
  `Definition`, `Location`, `Completion`). Shapes unchanged. `Location`,
  `Definition` and `phel index --output` columns are 1-based.
- In 0.50, `ExceptionPrinterInterface::printError()` and `printException()` were
  removed.
- In 0.51, Gacela's events are skipped unless the host sets
  `GacelaConfig::setEventDispatcher()`.
- In 0.52, `ErrorCode::INVALID_QUOTE`, `INVALID_UNQUOTE` and `INVALID_CHARACTER`
  were removed; Phel never raised them.
- In 0.53, `MetaInterface::withMeta()` always returns a copy (step 4). For atoms,
  use `Atom::alterMeta()` and `Atom::resetMeta()`.
- In 0.53, `Phel\Filesystem\FilesystemFacadeInterface` moved to
  `Phel\Shared\Facade\FilesystemFacadeInterface`, next to every other facade
  contract. `Phel\Fiber\FiberFacadeInterface` is gone: type-hint
  `Phel\Fiber\FiberFacade` instead.
- In 0.53, lint rule codes moved to the public `Phel\Shared\LintRuleCodes`; the
  strings are unchanged.
- After 0.54, `EmitterResult`, `ReaderResult` and `BuildOptions` moved to
  `Phel\Shared`; update the imports. `DynamicScope::$boundNames` and
  `Registry::$profilerHook` are private: use `DynamicScope::hasBoundName()` and
  `Registry::{getProfilerHook,setProfilerHook}()`.
- After 0.54, `Phel\Lang\LoadClasspath::NAMESPACE` is `LoadClasspath::NS`.
  PHP 8.6 deprecates a class constant named `namespace`, so no alias keeps the
  old name.

Only if you implement a facade interface yourself, these gained methods:
`ApiFacadeInterface` and `FormatterFacadeInterface::formatString()` (0.50),
`CompilerFacadeInterface::emptyNodeEnvironment()`, `enableDeprecationWarnings()`
and `replayDeprecations()` (0.51), `CommandFacadeInterface::getRuntimeErrorReport()`
(0.52), `CompilerFacadeInterface::findSimilarNames()`, `rejectSupersededForms()`
and `withoutDeprecations()` (0.54).

The four `FileIoInterface` renames of 0.50 sit under `Domain\` and were internal
all along.

From 1.0 on such changes need a major, and everything outside the
[public surface](../stability.md#public-php-api) carries `@internal`, so an IDE
and a static analyser will tell you when you reach for one. Depending on an
internal class is worth
[an issue](https://github.com/phel-lang/phel-lang/issues): it usually means a
facade is missing a method.

## What is *not* changing

- **Deprecated, still supported through 1.x**: the `\` namespace separator,
  `to-php-array` and key-first map destructuring (`{:a x}`). Each keeps working
  and has a replacement in [deprecated-surface.md](deprecated-surface.md).
- **Public `phel.*` names.** Every one is pinned in
  `tests/php/Integration/Api/core-api.snapshot.txt`; a test fails if a
  definition or arity disappears. Arities were narrowed once, in 0.50 (step 3).
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

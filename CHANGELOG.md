# Changelog

All notable changes to this project are documented in this file. Releases before 0.50.0 are in [docs/CHANGELOG-archive.md](docs/CHANGELOG-archive.md).

## Unreleased

### Highlights

- **BREAKING**: The compiler rejects code it used to accept with a different meaning, such as duplicate definitions, conflicting aliases, invalid bindings and malformed literals. Each case fails with a specific `PHEL` code; see Changed below.
- **BREAKING**: Sequence functions read maps and structs as `[key value]` entries, and `compare` and `sort` compare seqs element by element, as in Clojure.
- **BREAKING**: The command line has a stable contract: invalid invocations exit 2 with the error on stderr, machine output uses 1-based columns and environment switches are strict. Documented commands, options and machine output change only in major releases.
- Security: generated PHP lives in an owner-only temp directory, `phel.http-client` drops credentials on cross-origin redirects, `phel cache:clear` refuses unsafe paths and nREPL limits its input.
- Built apps reject a different Phel runtime version at startup instead of failing later.
- Float literals keep their exact value, `BigInt` arithmetic survives overflow, and `take`, `drop` and `range` accept ratio and big numbers.

### Changed

Compiler:

- **BREAKING**: Duplicates and name clashes fail instead of one silently winning. This covers definitions in `phel build` (`PHEL004`, as in `phel run`), namespaces that map to one PHP namespace (`a.foo-bar`, `a.foo_bar`), conflicting aliases, referred names and PHP `use` aliases (`PHEL004`), type class names that differ only in case (`PHEL004`), repeated `case` constants and protocol methods split across forms (`PHEL005`), and repeated map or set keys (`PHEL203`). Rename or remove one, and put every arity of a protocol method in one form: `(m [this] [this x])`. Repeating the same target, changing an alias in the REPL and reevaluating a `defstruct` still work. (#3703 #3649 #3524 #3718 #3568 #3607)
- **BREAKING**: Invalid names and type tags fail at the symbol with `PHEL007`: type names PHP rejects, such as `let`, `list`, `match` and PHP 8.6 reserved words (`enum`, `resource` and `numeric` stay valid), interface constants named `let`, `is` or `namespace`, a `def` targeting another namespace, and struct methods that replace built-in ones. Interface methods such as `Stringable` and magic methods such as `__invoke` in `:php` blocks still override. Unknown lower-case type tags fail with `PHEL001` and a suggestion; `^long`, `^double` and `^boolean` mean `int`, `float` and `bool`. (#3522 #3667 #3524 #3576)
- **BREAKING**: Malformed source fails with a located error instead of reading something else. Keywords without a name (`::`, `:a/`), invalid regex literals and numbers such as `08` or `1_` fail with `PHEL120`; a nested `#(` and `~` or `~@` outside quasiquote fail with `PHEL210`. Use `fn` for the inner function. (#3802 #3524 #3710 #3709)
- **BREAKING**: Invalid bindings, arities and forms fail instead of being dropped or padded. This covers destructuring and `catch` or `foreach` bindings (`PHEL008`), macro calls with the wrong arity (`PHEL002`), extra arguments to `if-not`, `if-let`, `if-some`, `assert` and `defexception`, incomplete pairs in `binding`, `with-redefs`, `doseq`, `dotimes`, `cond->` and `cond->>` (`PHEL005`), and `recur` inside `foreach`, `doseq`, `dotimes` or `dofor` (`PHEL010`). Put `loop` inside the body to recur there. (#3524 #3523)
- **BREAKING**: Special-form errors use specific codes instead of `PHEL007`: `PHEL002` for arity, `PHEL003` for wrong value kinds, `PHEL008` for invalid parameters and `PHEL001` for unresolved names. Lint reports these as `phel/arity-mismatch` and `phel/unresolved-symbol` instead of passing them. Update tools that match `PHEL007`. (#3603)

Runtime:

- **BREAKING**: Sequence functions read maps, sorted maps and structs as `[key value]` entries, as `seq` does; use `(vals m)` for values. `compare` and `sort` compare seqs, ranges, map entries and queues element by element, and a seq equals a vector with the same elements. Unequal maps, sets and transients throw; use `sort-by` for those. (#3556 #3570 #3567 #3555)
- **BREAKING**: `rand-int` excludes its upper bound; use `(rand-int (inc n))` for the old range. `take`, `drop`, `take-last`, `drop-last`, `take-nth` and `split-at` accept ratio and big counts and round them up; use `(int n)` for the old count. `partition` returns `()` for non-integer counts and rejects non-positive steps, and both it and `partition-all` reject non-positive sizes. (#3679 #3566 #3680)
- **BREAKING**: `phel.http-client` accepts only `http` and `https` URLs, rejects invalid headers and throws after 20 redirects. A cross-origin redirect keeps only content-negotiation headers, so credentials such as `x-api-key` no longer follow it. (#3531 #3687)

Tooling:

- **BREAKING**: Invalid invocations exit 2 with the error on stderr, and exit 1 means the command ran and found an issue. Environment switches accept `1`, `true`, `yes`, `on` and `0`, `false`, `no`, `off`; empty means unset and any other value exits 2. Lint, analyze, `api-daemon`, `Phel\Shared\Api\Diagnostic`, balance, mutate JSON and deprecation notices report 1-based columns (drop any `+ 1`), while the LSP stays 0-based. Analyze `uri` values are absolute paths and lint GitHub `file=` paths are relative; see `docs/cli-reference.md`. (#3525 #3691)
- **BREAKING**: Lint reports wrong-arity calls to keyword, collection and fn literals as `phel/arity-mismatch`; for a default value use `(get m k default)`. The new `phel/compile-error` rule reports every compile error, so a file that does not compile fails lint; turn it off with `{:rules {:phel/compile-error :off}}`. (#3524 #3621)

PHP API:

- **BREAKING (PHP API)**: `EmitterResult`, `ReaderResult` and `BuildOptions` move to `Phel\Shared`, and tooling facades are internal. `DynamicScope::$boundNames` and `Registry::$profilerHook` are private; use `hasBoundName()` and `getProfilerHook()`/`setProfilerHook()`. `LoadClasspath::NAMESPACE` is now `LoadClasspath::NS`. (#3521 #3522, ADR 0021)
- **BREAKING (PHP API, implementers only)**: `CompilerFacadeInterface::analyze()` accepts `mixed`, and `CommandFacadeInterface` gains `reportWarningsAtPhelSource()`. Callers are unaffected. (#3610 #3537)

### Added

Compiler:

- `declare` accepts several names, as in `(declare a b)`, and rejects non-symbol arguments. (#3524)

Tooling:

- `docs/stability.md` and `docs/cli-reference.md` define what changes only in major releases: commands, options, exit codes, machine output, `api-daemon` methods and environment variables, but not human-readable text. `api-daemon` and the LSP report the running version, and `phel mutate` takes `--format`, deprecating `--reporter`. (#3525, ADR 0022)
- Security reports go through GitHub private vulnerability reporting or email, and the README explains how to verify a release PHAR. (#3533)

PHP API:

- Built entry points, exported wrappers and `\Phel::assertBuiltWith(string $version)` reject a different runtime version; rebuild after upgrading. Builds warn when their version differs from the lock file, and public `Phel\Shared\VersionResolver` reports the running version. (#3519, ADR 0020)
- Generated PHP goes to an owner-only `<system temp>/phel-<uid>/tmp`, and Phel rejects temp directories another user owns or can replace. Public `PhelConfig::defaultTempDir()` and `Phel\Shared\CurrentUser` expose the directory and user. (#3532)
- Public `Phel\Shared\Parser\Node\ReaderConditionalNode` exposes the selected reader-conditional branch. (#3669)

### Removed

- **BREAKING (PHP API)**: The unused `DynamicScope::$anyActive` property, with no replacement. (#3521)
- The `phel/duplicate-key` lint rule; the reader rejects duplicate keys. A config that sets it is ignored. (#3607)

### Performance

- A warm `phel run` or `phel test` skips rescanning source directories, and a file without `ns` no longer forces a full reread (about 2x faster). (#3699 #3629)

### Fixed

Language:

- Reader: `\é`, `\😀` and escape pairs such as `\uD83D\uDE00` read as whole characters, strings with invalid UTF-8 compile as data, float literals keep their exact value, and large integer literals and arithmetic past the native range stay `BigInt`. Quasiquoted sets evaluate `~`, regex literals accept an escaped slash, symbols such as `foo#bar` round-trip, scripts may start with `#!`, and reader prefixes accept whitespace before their form. `(ns a/b)` fails with `Invalid namespace`. (#3708 #3711 #3664 #3522 #3665 #3676 #3706 #3799 #3800 #3801 #3714 #3713 #3668)
- Definitions report a clear compiler error: duplicate arities, fields, interface methods and parameters, non-symbol type names, empty symbols, malformed member names, wrong interface implementations and non-var `binding` targets. `(fn foo)` reports `PHEL002`, `(try ())` works, and recursive macro expansion stops after 1,000 levels. (#3534 #3803 #3716 #3720 #3667 #3537 #3646 #3647 #3648 #3719)
- Bindings keep their values when names repeat, share a PHP spelling or match superglobals; vector destructuring supports `:as`, and struct fields may contain an apostrophe. A namespace's own definitions win over `:use` aliases in bare values and calls, same-namespace type tags work outside builds, and class names such as `PDO` no longer warn in constructor, static or callable positions. (#3534 #3576 #3666 #3721 #3722 #3717 #3654 #3537)

Collections and values:

- Lazy seqs: `with-meta` copies share their values and run the body once, nested `lazy-seq` keeps its values, `iterate`, `repeatedly` and `cycle` realize only on demand, and `range` accepts ratio and big numbers. (#3806 #3678 #3557)
- Maps, vectors and structs: colliding hash keys with nil values are found, `pop` works at trie boundaries, floats hash by full value, structs stay distinct from maps, struct lookup accepts string keys, and `(merge-with f)` returns nil. (#3804 #3672 #3673 #3677 #3674 #3675 #3680)
- Printing: `pr-str` and `str` keep full float precision. `pr-str` prints `##NaN` and `##Inf`, escapes control characters, and shows resources as `#<PHP Resource id #N>` and cyclic atoms as `#<cycle>`. `(symbol "/a")` keeps its name, `name` on nil raises a clear error, and `with-meta` accepts fn literals. (#3707 #3712 #3705 #3524 #3710 #3680 #3537)
- Libraries: `phel.json/encode` writes `{0 :a 1 :b}` as an object and throws for values JSON cannot hold exactly, regex functions return nil for unmatched groups and find later alternatives, `phel.html` `when` renders every body form, a keyword with a NUL namespace stays distinct, and `parse-long` returns nil for a number outside the int range. (#3807 #3808 #3809 #3810 #3805 #3812 #3857)
- HTTP and files: `phel.http-client` sends `:query-params` before a URL fragment, keeps credentials on same-origin redirects and accepts `:max-body-bytes`; `uri-from-string` throws its documented exception; `file-seq` stops at symlink cycles. On PHP 8.6, struct predicates, interface calls and object hashes emit no deprecations, and functions returning `(php/=& ...)` work with OPcache. (#3822 #3688 #3811 #3537 #3522)

Builds and caches:

- Caches survive concurrent processes and interrupted writes, and same-second edits and macro edits refresh every dependent namespace. `.phel/` ignores itself, and `phel --version`, `help` and `list` no longer create it. (#3537 #3564 #3553 #3698 #3700 #3690)
- Builds: PHARs leave out untracked files and have reproducible checksums, built apps find `load` files and bootstrap `eval`, `future-call` and `phel.edn`, Composer installs no longer warn about duplicate namespaces, and running from a subdirectory finds the project. `phel cache:clear` refuses unsafe paths, and the Docker image installs its tagged release on amd64 and arm64. (#3697 #3528 #3542 #3527 #3549 #3701 #3532 #3702 #3536)

Commands and tests:

- `phel run` accepts options before the script and global options before `run`, and `--` ends options. `phel eval` rejects blank input, `phel config` shows environment overrides, and `phel profile` tracks fibers and exits 1 when it cannot write its report. (#3537 #3689 #3814 #3695 #3525 #3816 #3815)
- Test output: `thrown-with-msg?` accepts a regex, an exception outside `is` counts as an error, parallel and serial JUnit match, machine output keeps status on stderr, and mutation workers no longer hang. (#3813 #3537 #3627 #3642 #3525 #3526 #3696)

Editors and diagnostics:

- The LSP handles UTF-16 positions after non-ASCII text, CRLF files, unsaved edits, Windows file URIs and invalid UTF-8. `phel watch` reloads with the inotify, fswatch and polling backends. (#3821 #3683 #3685 #3686 #3684 #3818 #3819 #3820)
- `phel format` keeps every reader-conditional branch and comment and aligns Unicode names by character. nREPL limits input, stops cleanly on SIGTERM and warns when bound outside loopback. (#3669 #3670 #3715 #3671 #3681 #3682 #3575 #3532)
- Errors from `run` and `test` name the calling `.phel` file and line, and keep their `PHEL` code. Lint and analyze follow `load` files, skip quoted data, handle same-file macros and `#inst`, and report an arity error once. `phel balance --fix` refuses a prefix missing its form, `phel doc` links the right guides, and command help shows `phel ...` examples. (#3537 #3608 #3551 #3550 #3621 #3610 #3655 #3817 #3656 #3645 #3563 #3577)

PHP API:

- `Seq::range()` accepts any Phel number, and `SequenceGenerator::numericRange()` is public. (#3557)

## [0.54.0](https://github.com/phel-lang/phel-lang/compare/v0.53.0...v0.54.0) - 2026-10-04

### Added

Language:

- `defmulti` takes Clojure's signature, `(defmulti name docstring? attr-map? dispatch-fn & options)`, with `:default` for the fallback dispatch value and `:hierarchy` for an atom or var holding a hierarchy. An unknown option fails with the supported list. (#3415)
- A protocol extended to a PHP interface or parent class applies to every class that implements or extends it: `(extend-type \Countable Sized ...)` covers `\SplObjectStorage`. The exact class wins, then the nearest parent, then an interface, then `:default`. `\ArrayObject`, a `:use`d `ArrayObject` and `"ArrayObject"` register the same key; a symbol used to register under the current namespace and never dispatch. (#3413)
- `reify` implements PHP interfaces, alone or next to protocols: `(reify \JsonSerializable (jsonSerialize [this] 5))`. It used to fail with a PHP parse error. Methods of `reify`, `defstruct` and `defenum` declare the interface's return type, so PHP no longer prints `Deprecated: Return type ... should either be compatible`. (#3412)
- `phel.core` has `with-out-str`, `subs`, `list*`, `qualified-keyword?`, `qualified-symbol?`, `hash`, `comparator`, `load-string`, `pcalls`, `pvalues`, `inst?`, `halt-when`, `ensure-reduced`, `partitionv`, `partitionv-all` and `replace`, as in Clojure. Core `subs` throws on a nil index; `phel.string/subs` keeps reading nil as the bounds. (#3417 #3418 #3437)
- `map-indexed`, `partition-by` and `partition-all` return a transducer when called without a collection: `(into [] (partition-by odd?) [1 3 2 4 5])` returns `[[1 3] [2 4] [5]]`. (#3437)

Tooling:

- `PHEL_OPTIMIZATION_LEVEL` sets the optimization level for every command of a process, over `phel-config.php`. `phel build -O` still wins for its build. (#3396)
- A file whose first form is not `(ns ...)` fails with `'src/app.phel' does not start with an (ns ...) form`, instead of a `Cannot resolve symbol 'defn-'` hint. A directory scan, such as the one `phel eval` runs over the working directory, skips such a file. (#3373 #3484 #3515)
- Lint rule `phel/shadowed-core-fn` warns when a `let`, `loop` or `fn` binding is named after a public `phel.core` fn, as in `(let [inc (fn [x] 99)] (inc 1))`. (#3374)
- Editor completion after `(.method` knows a receiver built with `(new Foo)`, `(Foo.)` or `(Foo/make)`, bound or inline. `(new Fo` completes class names, and `(DateTimeImmutable/` completes static members without a `:use`. (#3398)
- `phel doc reduce-kv` prints the full doc of `reduce-kv`: signatures, docstring, example and see-also. A search with no exact name, such as `phel doc mapp`, says so before the table of candidates, and the table no longer wraps a function name mid-word. (#3474)
- `find-doc` in the REPL prints the doc of every public definition whose name or docstring matches a string or regex, as in Clojure: `(find-doc "partition")` lists `partition`, `partition-all` and `partition-by`. (#3473)
- `phel doc --format=json` names the namespace to require: `upper-case` carries `"requireNs": "phel.string"` and `"require": "(:require phel.string :refer [upper-case])"`. A `phel.core` fn carries `requireNs` and a null `require`. (#3466)
- `phel lint --format=json` and `phel analyze` report one error with the same fields. Each diagnostic adds `errorCode` (`PHEL001` behind `phel/unresolved-symbol`), `suggestions` (the "did you mean" names as a list) and `fix` (the catalog's advice). `phel analyze src` walks a directory and exits 1 on an error. `phel explain phel/unused-require` explains a lint rule, and `--format=json` prints any entry as JSON. A `docs/` path in a fix names the file from the project, `vendor/phel-lang/phel-lang/docs/...`. (#3464)

PHP API:

- **BREAKING (PHP API, implementers only)**: `CompilerFacadeInterface::withoutDeprecations()` runs a callable with deprecation notices held back. (#3381)
- **BREAKING (PHP API, implementers only)**: `CompilerFacadeInterface::rejectSupersededForms()` throws on a superseded form in a form the reader returned. (#3456)
- **BREAKING (PHP API, implementers only)**: `CompilerFacadeInterface::findSimilarNames()` returns the analyzer's "did you mean" candidates for a name. (#3454)
- **BREAKING (PHP API, implementers only)**: `CompilerFacadeInterface::deprecationWarningsEnabled()` says whether a compile in this process raises deprecation notices. (#3525)
- Public PHP API: `Phel\Lang\Destructure` (with `nthNext()`, `kwargs()`, `lookupSource()` and `restKwargs()`), `Symbol::{createGenerated,isGenerated}()`, `LintFacade::ruleExplainer()`, `Diagnostic` error codes, suggestions, fixes and projections, `AbstractLocatedException::{getSuggestions,setSuggestions}()`, `LintRuleCodes::SHADOWED_CORE_FN`, the `VersionResolver` constructor, `\Phel::fnSlot()`, `Phel\Lang\ForeignFn`, `Phel\Shared\OptimizationLevel`, `Phel\Shared\SourceMap\SupersededSourceMaps`, the `MissingNsFormException` / `MissingNsFormHint` pair and `ClassNotFoundHint` in `Phel\Shared\Exceptions`, and `LintRuleCodes::UNKNOWN_CLASS`, `UNRESOLVED_NAMESPACE` and `UNRESOLVED_REFER`. (#3354 #3356 #3373 #3396 #3435 #3454 #3455 #3465 #3479 #3487)

### Performance

Compiler:

- A multi-arity fn called as a value (passed to `reduce` or `map`, or called as `(f x)` on a local) takes its arguments as fixed params instead of packing them into an array: `(reduce + 0 v)` is 7% faster, `(f acc x)` on a local bound to `+` 18%. (#3469)
- A build calls a multi-arity fn's fixed arity directly, also when the fn has a variadic arity: `(+ acc x)` in a loop is 1.6x faster. Nested calls such as `(-> m (get :a {}) (get :b {}))` grow linearly instead of doubling per level. (#3354)
- Comparisons, `=`, `zero?`, `pos?`, `neg?`, `inc` and `dec` on values of unknown type answer inline for native ints: `(< a b)` 7.9x faster, `(= x 3)` 8.8x. (#3351)
- `case` and `cond` over keywords look each keyword up once per fn: a five-arm keyword `case` is 10x faster. (#3360)
- A multi-arity fn built at runtime, such as what `comp` or `partial` return, allocates one closure: `(comp f g)` created and called is 2.3x faster, with peak memory down from 125MB to 20MB. (#3355)
- Sequential destructuring reads a vector by index: `(let [[a b] v] ...)` is 3.2x faster. (#3356)
- `(not x)` compiles to an inline nil/false check, 2.6x faster; an `if` over a `^bool` param skips the truthiness check. (#3352 #3353)
- A namespace loaded from the compiled-code cache registers its definitions with one call per file instead of a guarded block per `def`: loading a file of 500 `def`s takes 1.33 ms instead of 2.89 ms, and a warm `phel run` peaks 0.3 MB lower. The cache key hashes the source with `xxh128` instead of `md5`, 26x faster; existing caches rebuild once. (#3470)
- Binary `+`, `-` and `*` on values of unknown type answer inline for native ints: `(+ a b)` is 4x faster, `(* a b)` 6x. An overflow still promotes to `BigInt`, and floats, `BigInt` and nil take the runtime fn as before. (#3468)

CLI:

- `phel` restarts PHP for the opcache file cache only where it pays off: never for `--version`, `list`, `help` or `completion`, and on macOS only for `test`, `build`, `bench`, `mutate`, `profile` and `export`. `phel --version` on macOS drops from 125 ms to 87 ms. `PHEL_OPCACHE_REEXEC=1` restarts for every command. (#3425)
- Commands that do not print the version start no `git` process, saving about 20 ms each. (#3407)
- A warm `phel run` or `phel test` no longer walks Phel's own PHP sources to validate the namespace scan cache: a one-file `phel run` drops from 110 ms to 103 ms. (#3467)

### Fixed

Compiler:

- **BREAKING**: a map or set literal that repeats a constant key fails with `[PHEL203] Duplicate key: :a`. `{:a 1 :a 2}` used to read as `{:a 2}`. (#3387)
- **BREAKING**: the argument count check (`PHEL002`) rejects too many arguments, as it does too few, and covers namespaces with a `-` and fns defined earlier in the same file under `phel compile`. `(defn sq [n] (* n n)) (sq 3 4)` used to run. `^:dynamic` and `^:redef` fns stay unchecked. (#3384 #3394)
- **BREAKING**: map destructuring reads namespaced keys as Clojure does: `{:my/keys [a]}` and `{:keys [my/a]}` read `:my/a`, `{::keys [a]}` reads `::a`. `{:my/keys [a]}` used to read `:a`; write `{:keys [a]}` for that. (#3414)
- **BREAKING**: a `:refer` of a name the required namespace does not define fails at the `ns` form: `(:require app.util :refer [f nope])` reports `[PHEL013] 'nope' is referred from app.util, which does not define it.`, and a private name fails the same way. Drop the name from `:refer`, or define it. Lint rule `phel/unresolved-refer` reports it. It used to surface only when `nope` was called, or never. `phel.test` defines `thrown?`, `thrown-with-msg?` and `output?` so `(:require phel.test :refer [is thrown?])` keeps working; outside `is` they fail with a message naming `is`. (#3455)
- A `^void` fn compiles and returns nil. It used to emit `return <value>` in a PHP `: void` function. (#3363)
- A fn whose body contains `php/yield` compiles. It used to get its tail's return type, which PHP rejects on a generator. (#3365)
- A call to a `^:dynamic` or `^:redef` fn in a build sees `binding`. It used to keep the first value it saw, and `-O2` inlined short `^:dynamic` fns past `binding`. (#3367)
- `(def- x "doc" 1)` binds `x` to `1` with `"doc"` as its docstring. It used to bind `x` to `"doc"`. (#3372)
- `(let [a false] (if a a a))` returns `false`. It used to return `nil` with an `Undefined variable` warning when the other branch read the local again. (#3383)
- A symbol that does not resolve gets suggestions from where it says it lives. `(str/join "," [1 2])` with no require fails with `No namespace or alias 'str'. Did you mean (:require phel.string :as str)?`, `(s/uper-case "a")` suggests `s/upper-case`, and a bare `upper-case` names `(:require phel.string :refer [upper-case])`. A qualified symbol used to get unrelated core names, or no suggestion at all. (#3458)
- `#inst` accepts every prefix of the full timestamp, as Clojure does: `#inst "2026-03-04"`. An out-of-range field such as `2026-02-30` is still rejected. (#3416)

Runtime:

- **BREAKING**: `transduce` calls the reducing fn's completion once, as Clojure does: a reducer with a 1-arity finalizes the result. A reducer with only a 2-arity now throws `ArgumentCountError`; wrap it in `completing`. (#3433)
- Map destructuring reads a seq as keyword arguments and anything else through `get`, as Clojure does: `(let [{:keys [a]} '(:a 1)] a)` and the same over `(map identity [:a 1])` return `1`, and `(let [{:keys [a]} #{:a}] a)` returns `:a`. A set or a lazy seq used to fail with `Cannot use object ... as array`. A map keeps its inline lookup. (#3479)
- `seq` and `next` keep a lazy seq or list whose next element is nil, as in Clojure: `(next (map identity [1 nil 3]))` returns `(nil 3)`. They used to return nil and drop the rest, so `(reduce + (map :price items))` stopped silently at the first missing key. (#3451)
- Map destructuring of a vector or list returns nil for a key that is not an index, as `get` does: `(let [{:keys [a]} [1 2]] a)` returns nil. It used to print a notice and throw a `TypeError`. `(contains? [1 2] :a)` returns `false`; it used to return `true`. (#3475)
- `(seq 5)` fails with `Don't know how to create a seq from: int`. It used to blame `apply`, which the user never called. (#3459)
- A runtime arity error names the Phel fn: `[PHEL401] Wrong number of args (1) passed to app.main/add, expected 2`. It used to print PHP's `Too few arguments to function Phel\Lang\AbstractFn@anonymous::__invoke()` with the path of the PHP file that made the call. (#3459)
- `count` counts the seq `next` returns for a lazy seq: `(count (next (map identity [1 2 3])))` returns `2`. It used to throw `count is not supported on a lazily consumed source`. (#3452)
- `declare` keeps the value of a symbol that is already defined, as in Clojure. Loading a namespace again used to reset what it declares to nil, so a long-lived process (REPL, `phel watch`, nREPL) could fail with `Value of type null is not callable` while recompiling `phel.core`. (#3444)
- `partition-all` throws for a size or step that is not a positive int. A size of 0 used to return an empty seq, and a step of 0 never ended. (#3437)
- The `dedupe` transducer keeps a leading `:phel/none`. It used to drop it. (#3437)
- `with-output-buffer` closes its buffer when the body throws, and restores the buffer level it found. It used to leave the buffer open. (#3417)
- `format` and `printf` convert a ratio, bigint or bigdec: `(format "%.2f" (/ 250 100))` returns `"2.50"`. It used to print `"1.00"` with a warning. (#3386)
- `(gensym "tmp")` returns `tmp42`-style names, as in Clojure. It used to ignore the prefix. `(break)` hides the locals `gensym`, `x#` and `#(...)` create, and lists every local you bind. (#3385)
- A map pattern after `&` reads the rest arguments as keyword arguments, as Clojure does: `((fn [& {:keys [a]}] a) :a 1)` and `((fn [& {:keys [a]}] a) {:a 1})` return `1`, and a trailing map merges over the pairs. It used to look the keys up in the rest vector and fail. (#3487)

Tooling:

- `phel --version` reports Phel's own commit. Inside another git repository it used to print that repository's commit. (#3407)
- An edit to `phel-config-<env>.php` takes effect on the next run. It used to be ignored until `phel cache:clear`. (#3426)
- A stack trace maps every frame to its `.phel` source after an edit to a file loaded with `(load ...)`, and after a recompile in the REPL, `phel watch` or nREPL. Frames used to show `.phel/cache/compiled/` paths or the new code's lines. (#3430 #3435)
- The PHAR's precompiled stdlib names its sources inside the PHAR. It used to carry the release builder's absolute paths, so a deprecation raised inside the stdlib reached the user from the PHAR while a source checkout hid it. (#3448)
- `phel test` in parallel mode reads worker stderr as it arrives. On macOS a worker writing over 16 KB to stderr used to crawl. (#3378)
- `phel run` prints a backslash separator notice once per loaded file, with a relative path. It used to warn for every `.phel` file under the working directory, on every warm run. (#3381)
- `phel mutate` compiles at optimization level 0 whatever the config says. At level 2 every mutant used to survive. (#3396)
- `phel doc --format json` and the API reference link special forms to their current guide sections, and `:see-also` entries name real fns. (#3371)
- `build/preload.php` preloads every module's pillars, found at startup. The hand-kept list missed eight modules and named three deleted files. (#3410)
- A top-level `(require phel.string :as s)` in a file fails with `require is only available in the REPL; use (:require ...) inside ns`. It used to suggest `Did you mean 'reduce'?`. (#3476)
- `phel init` writes a `composer.json` requiring the running Phel, such as `"phel-lang/phel-lang": "^0.54.0"`, so the `composer install` step it prints works. An existing `composer.json` is kept, even with `--force`. (#3472)
- A failing `(is (= expected actual))` prints `expected:` and `actual:`, and its diff names the path of each change inside nested maps and vectors: `~ [:b :c 2] 3 -> 4`, `+ [:b :e] 5`, `- [:x] 1`. The diff used to repeat the whole subtree under the top-level key. A failing `(is (not (= a b)))` now says `but is: = to` instead of `but is not: = to`. (#3471)
- `phel eval` and `phel run` print values and errors without colour codes when stdout is not a terminal, as in `phel eval '#{1 2}' | cat`. `--ansi` forces colour; `--no-ansi` and `NO_COLOR` now remove it from printed values too. (#3462)
- `phel lint`, `phel analyze`, the LSP and the api-daemon report `(php/new \DateTime)`, `php/->`, `php/::` and `set-var` as `PHEL012`, as `phel run` does. They used to report the file as clean. (#3456)
- `phel lint` re-lints every file once after a Phel upgrade. A file cached as clean used to stay clean when the new release reports something more, until it changed or `--no-cache` was passed. (#3478)
- A require of a `phel.*` or `clojure.*` namespace that Phel does not ship fails at the `ns` form: `(:require phel.strng)` reports `Cannot find namespace 'phel.strng' required by 'app.main'. Did you mean 'phel.string'?`, and lint rule `phel/unresolved-namespace` flags it. It used to load nothing and fail later with `Cannot resolve symbol`. `clojure.set` resolves to `phel.core`, so `(set/union #{1} #{2})` returns `#{1 2}`. (#3454)
- A file whose `ns` form does not analyse no longer breaks every command. `phel lint` reports it as a diagnostic (`PHEL007 :refer :all is not supported...`, in JSON with `--format=json`) and lints the other files, `phel run` of a file that does not require it works, and `phel build` fails naming the file and line. All three used to stop with a bare `Refer must be a vector` and no file. (#3457)
- `(Integer/parseInt "3")`, `(Math/abs -1)`, `(System/currentTimeMillis)`, `(Thread/sleep 1)` and `(String/valueOf 1)` end with a hint naming the Phel replacement: `hint: Integer is a Java class, not a PHP one: for Integer/parseInt use (parse-long s).` `(.toUpperCase "abc")` points at `phel.string` instead of leaving `Class "abc" not found` alone. Lint rule `phel/unknown-class` warns about a static call to a class it cannot autoload. (#3465)
- A require of a missing namespace reports `[PHEL014]` with the requiring file, line and a caret on the namespace, plus the directories searched. It used to point at Phel's own `ExtractorException.php`. `phel explain PHEL014` describes it. (#3461)
- Errors and stack frames name a Phel var as `ns/name`: `Wrong number of arguments to function "bugs.arity/add"`, `(bugs.rt2/step [1 2])`, `(phel.core/* 2 nil)`. They used to print `bugs.arity\add` and `bugs\rt2\step`, which raise the backslash deprecation when pasted back into code. (#3460)
- REPL and nREPL completion insert a qualified name as `phel.string/upper-case`. It used to insert `phel.string\upper-case`, which prints the backslash separator deprecation. A prefix typed with `\` still matches. (#3500)
- `phel init` writes `(defn greet [who] ...)`, so a fresh project passes `phel lint`. It used to warn that `name` shadows a core fn. The docs `phel agent-install` copies teach a check loop (`phel lint --format=json`, `phel explain`, `phel doc --format=json`), point at `vendor/phel-lang/phel-lang/src/phel/` instead of a `src/phel/core/` a project does not have, list the Clojure habits that do not work in Phel, and no longer append phel-lang's own commit rules. (#3463)
- Two `phel` processes that compile the same code at the same time, as `phel test --parallel` workers do, no longer see a defined fn as nil. They shared one temp file per compiled form, so one could read the other's empty or half-written file and fail with `Value of type null is not callable`. (#3495)

## [0.53.0](https://github.com/phel-lang/phel-lang/compare/v0.52.0...v0.53.0) - 2026-09-24

### Changed

- **BREAKING**: PHP 8.5 is the minimum. The floor moves now: once `1.x` starts, raising it needs a major. (#3302)
- **BREAKING (PHP API)**: `FilesystemFacadeInterface` moves from `Phel\Filesystem` to `Phel\Shared\Facade`. `Phel\Fiber\FiberFacadeInterface` is gone: type-hint `Phel\Fiber\FiberFacade` instead. (#2859)
- **BREAKING (PHP API)**: `MetaInterface::withMeta()` always returns a copy. Symbols, keywords, atoms and functions are no longer changed in place, so keep the returned value. For atoms, use `Atom::alterMeta()` and `Atom::resetMeta()`. (#3311, ADR 0019)
- **BREAKING (PHP API)**: lint rule codes move to the public `Phel\Shared\LintRuleCodes`. The strings stay the same, so `phel lint --format=json` consumers keep working. Renaming a code is now a breaking change. `Phel\Shared\Exceptions\ErrorCode` is public too. (#3309)

### Added

- Short type tags for Phel values: `^map`, `^vector`, `^set`, `^list`, `^keyword`, `^symbol` and `^atom`. `?map` and `map|null` work too. `(defn f [^map m] (:k m))` gets the fast `->find` lookup without the full interface name. See "Type tags" in `docs/spec/language-surface.md`. (#3319)
- Collection methods that return a copy are `#[NoDiscard]`. Dropping the result of `(.put m k v)`, or of an inlined `(conj v x)` / `(assoc m :b 2)` outside tail position, now warns instead of doing nothing. Cast to `(void)` when it is deliberate. Transients are exempt, except `TransientArrayMap::put`, which can return a new transient. (#3303)
- `phel bench --ab=<git-ref> --pairs=N` compares the working tree with a git ref, one A/B run per pair. Each row shows the mean delta, or `noise` when the pairs disagree on the sign. `--tolerance` fails only when every pair is slower by more than the tolerance. The working tree is never touched. See `docs/benchmarking.md`. (#3324)
- Public PHP API, since compiled code calls it: `Phel\Lang\GetIn`, `Phel\Lang\AssocIn` and `Phel\Lang\Collections\ValueIdentity`. (#3320 #3321 #3328)

### Performance

Literal call shapes compile to direct code, with the same results. A key list from a variable, `apply`, or a local that shadows the core fn keeps the runtime call.

- `(assoc m k1 v1 k2 v2 ...)`: one step per pair, 3.4x to 3.7x faster. `(assoc! t ...)` is 3.4x faster. The `transient` docstring now says when a transient pays off: about eight writes on a map. (#3317 #3318)
- `(get-in m [k1 k2 ...])`: 2.9x faster for two keys, 4.5x for eight keys with a default. (#3320)
- `(assoc-in m [...] v)` and `(update-in m [...] f & args)`: 1.9x to 2.8x faster. (#3328)
- At optimization level 2, `(update m k (fn [v] ...))` in tail position inlines a simple literal fn: no closure, about 9% faster on a 90-key map. Off at the default level, for a `:redef` `update`, and for a body that could notice it runs in the caller's frame. (#3322)

Runtime:

- `assoc` of the value an index already holds returns the same vector: 2.4x faster on 100 elements. Same for `rest` / `subvec` vectors and structs. A new value costs about 3% more on a vector and 5% less on a hash map. (#3321)
- `into` with a transducer, the core transducers and `reduce` build fewer closures. `(into [] (comp (map f) (filter :alive)) coll)` drops from 14.2μs to 11.8μs, `(reduce + 0 v)` over 32 ints from 9.6μs to 6.8μs. One change, toward Clojure: a custom transducer that calls its reducing fn with extra arguments reaches `conj`'s variadic arity instead of throwing. (#3323)

### Fixed

Compiler:

- **BREAKING**: an octal escape above `\377` is a compile error. It used to wrap silently: `"\400"` compiled to NUL. Clojure rejects it too. Char-literal errors now show file, line, snippet and caret. (#3301)
- A bare tag after a `:use` import, `:as` alias included, resolves to the imported class. It used to reach the generated PHP unqualified and fail at call time. The tag resolves where it is declared, so a caller mapping the same alias to another class no longer infers the wrong type and throws `TypeError`. Union and intersection tags on `defstruct` and `definterface` resolve the same way. (#3319)

Collections:

- `assoc` on a map keeps a new value that is `=` to the stored one but not identical: `-0.0` over `0.0`, a `BigInt` over an equal `int`, a vector with new metadata. The unchanged check compares by identity, as Clojure does. A PHP array always counts as a change, so two recursive arrays no longer throw `Nesting level too deep`. Applies to every map type, transients, structs and vectors. (#3321)

Tooling:

- `phel test --parallel` workers run with the OPcache JIT off. A php.ini that enables the JIT, as on every GitHub runner, crashed workers with SIGBUS on arm64 macOS. A dead worker now reports its exit code or signal and its output. Also fixed: a large stderr write no longer hangs the run, stray output counts as a crash, and a namespace that kills its worker on every retry no longer stops the rest of the queue. (#3334)

## [0.52.0](https://github.com/phel-lang/phel-lang/compare/v0.51.0...v0.52.0) - 2026-09-19

### Added

- `phel explain <code>` prints what an error code means, the smallest program that raises it, and the fix. The same text lives in `docs/errors/`. (#3266)
- Runtime errors carry a code: `PHEL400` not callable, `PHEL401` arity, `PHEL402` type, `PHEL403` out of bounds, `PHEL404` division by zero. (#3266)
- `--stack-trace` on `phel run`, `phel eval` and `phel repl`. The collapse marker names the flag and the error log. (#3261)
- **BREAKING (PHP API, implementers only)**: `CommandFacadeInterface::getRuntimeErrorReport()` returns the runtime error report as a string, for hosts that print it themselves. (#3264)
- Public API: `Phel\Lang\PhelType`, `Shared\ByteSize`, `Shared\Performance\OpcacheFileCache`, `Shared\Performance\OpcacheFileCachePruner`, `PhelProjectDirectory::opcachePath()` and `ErrorCode::SUPERSEDED_FORM`. (#2859 #3268 #3290)

### Removed

- **BREAKING**: `php/new`, `php/->`, `php/::` and `set-var` as source. Writing one is a `PHEL012` error. Use `(new \Foo arg)`, `(.method obj arg)` / `(.-field obj)`, `(\Foo/method arg)` / `\Foo/CONST`, and `(alter-var-root (var v) f)`. The compiler still emits all four, so generated PHP does not change, and macros that expand to them keep working. The rest of `php/*` stays. (#2859, ADR 0018)
- **BREAKING (PHP API)**: `ErrorCode::INVALID_QUOTE`, `ErrorCode::INVALID_UNQUOTE` and `ErrorCode::INVALID_CHARACTER`. Phel never raised them. (#3266)

### Fixed

Compile errors:

- Every compile error reports the same way: code, file and line, snippet, caret. Lexer errors (`PHEL310`), unclosed delimiters and redefined symbols (`PHEL004`) used to print the exception class, an internal file, or 31 frames. An unclosed delimiter names the line it opened on, a redefinition the line of the first one. (#3259 #3267 #3289)
- Errors that had a code but never printed it now do: `PHEL008` bindings, `PHEL009` interfaces, `PHEL010` `recur`, `PHEL202` splice. (#3266)
- A special form with too few arguments names the form and the shape it wants. It used to raise `[PHEL403] Index out of bounds` from inside `PersistentList`, or a bare `AssertionError` that `zend.assertions=-1` hid. Same fix for a `definterface` method with no argument vector and a `catch` with no type or binding. (#3266 #3297)
- Analyzer messages name Phel types: `nil`, not `null`. `vector`, not `Collections\Vector\PersistentVector`. (#3290)
- `(.-$foo o)` explains the sigil rule instead of `Cannot resolve symbol '.-$foo'`. Diagnostics suggest the Clojure-style spelling, never `php/::` or `php/new`. (#3300)
- A compiler deprecation prints one stderr line naming the user's file. (#3262)

Runtime errors:

- An uncaught runtime error reads the same in `phel run`, `phel eval` and the REPL: message, `at` line, user frames, one collapse marker, a hint when one matches. (#3264)
- An error raised inside the core library points its `at` line at the user's call, not the compiled cache. An uncaught `ex-info` prints its data map. (#3260)
- A host object with no printer of its own prints as `#<Class>`, so a stack-trace frame no longer shows a sentence where an argument belongs. (#3265)
- The error log is plain text. Each entry opens with a timestamp and the command, and the file rotates at 1 MiB into `error.log.1`. (#3269)

Tooling:

- `phel lint` reports a file that does not lex or parse and exits 1. It used to print "No lint issues found." and exit 0. (#3292)
- `phel test`: the `Form:` line of a failed assertion shows the source form, not the evaluated value. (#3263)
- `phel test --parallel` warms the bundled namespaces first. It re-runs a namespace only when its worker died, never for a compile error or a failing test. (#3271)
- `phel mutate` and `phel test --coverage` collect coverage under pcov. A run that reaches no mutant scores 0 and fails `--min-covered-msi`. (#3270)
- REPL: `(exit)` and `(quit)` end the session, and `(exit <code>)` sets the exit status. (#3272)

OPcache file cache:

- `<phel-dir>/opcache` stops growing without bound. `phel cache:clear` empties it, every run prunes stale system-id directories and orphaned `.bin` files, and `phel doctor` reports its size. (#3268)
- Pruning no longer dies with `UnexpectedValueException` when another process removes a subdirectory mid-prune. (#3280)

## [0.51.0](https://github.com/phel-lang/phel-lang/compare/v0.50.0...v0.51.0) - 2026-09-05

### Added

- `phel mutate`: mutation testing for Phel with 11 mutators, parallel and changed-file modes, coverage selection, and MSI gates. (#3208 #3209)
- `phel test` gains `--changed[=<ref>]`, `--reporter=github`, per-test coverage JSON, `^:skip` / `^:focus` / `skip!` with `--fail-on-focus`, and `phel.test/*event-hook*`. (#3207 #3209 #3210 #3211 #3214)
- `phel format --exclude=<glob>` and the matching `format-exclude` config key. (#3233)
- `cache-env-vars`: environment values that take part in the compiled-code cache key. (#3236)
- Clojure-style binding-first map destructuring, nested bindings included. (#3115)
- `^:redef`, which keeps a call interceptable where `-O2` would inline it. (#3126)
- CSS-style id and class shorthand in `phel.html` element tags. (#3240)
- `phel.ai/*sleep-fn*`, a bindable retry backoff seam. (#3204)
- A bare local `defexception` name now resolves in `catch`. (#3211)
- Public API: `Shared\Process\PhelBinaryLocator`, `Shared\DeprecationResolver`, per-test coverage, and immediate compiled-code cache flushing. (#3203 #3209 #3243)

### Changed

- Render the changelog's `### Performance` section in the GitHub release notes, and stop listing the same contributor twice when their commits carry different capitalisation. (#3258)
- `tools/release.sh` accepts a pre-release version (`1.0.0-rc1`): it leaves `## Unreleased` in place and publishes the GitHub release as a pre-release. (#3253)
- Document the two release-time documentation steps no tooling performs, and mark the paragraph in the stability policy that flips at a major. (#3254)
- `Registry::addDefinition()` also accepts a `Closure` returning the metadata map. Widening a parameter is not breaking, and the eager form still works, so an artifact compiled earlier stays loadable. (#3250)
- Resolve bare all-caps PHP names by position instead of autoload state. See [ADR 0016](docs/adr/0016-a-bare-all-caps-host-name-reads-by-position.md). (#3064)
- Expose compiler environment and deprecation controls through `CompilerFacadeInterface`. (#3048)

### Performance

Compiler:

- Evaluate the emission already produced instead of emitting every form a second time: 18% faster to compile and evaluate. (#3249 #3257)
- Emit each form once under `phel compile --emit-only`: 24% faster, byte-identical output. (#3246)
- Reuse the lists a macro expansion leaves unchanged: 5% faster analysis of macro-heavy source. (#3246)
- Resolve a call's `phel.core` identity once per node rather than once per specialisation probe: 4% faster emission. (#3246)
- Compile `is` arms through one runtime helper, cutting generated size, compile time and memory. (#3212)
- Inline `-O2` calls in return position. (#3125)

Runtime:

- Answer `contains?`, map lookups and realized lazy sequences without repeating work already done: up to 22% faster. (#3245)
- Promote every map construction path at the same size, making grown map reads up to 5.5x faster. (#3172)
- Read non-dynamic globals straight from the registry: 1.9x. (#3179)
- Spread vectors into `apply` in one pass: 2.1x. (#3181)
- Give hot core functions fixed arities: up to 22x. (#2973)

Standard library:

- Build `mapv` and `filterv` directly instead of through a lazy sequence `vec` then tears down: 3.5x and 2.8x. (#3251)
- Give `update-in` the fixed arities `update` has: 32% faster, and `assoc-in` 27%. (#3251)
- Build the walked list and the `pprint` joins once instead of through a spread: 57% and 23% faster. (#3248)
- Write `into`'s targets through transient methods, and reverse and escape natively in `phel.string`. (#2973)

Test framework:

- Record an assertion against one rebuild of the counts map instead of two nested walks, halving the per-assertion bookkeeping. (#3247)

Boot:

- Carry a definition's metadata as a thunk forced on first read, so loading a namespace no longer builds a map per definition: `phel.core` loads 20% faster, peak memory 22MB to 20MB. (#3250)
- Skip Gacela's event dispatch when no listener is registered; a host that wants the events sets `GacelaConfig::setEventDispatcher()`.

### Deprecated

- `to-php-array`; use its Clojure-aligned alias `to-array`. (#3076)
- Key-first map destructuring (`{:key local}`); use binding-first (`{local :key}`). (#3115)

### Fixed

- Name the namespace and the fix when an exported wrapper cannot resolve its definition, instead of failing with `Value of type null is not callable`. (#3244)
- Iterate a hash map's `nil` key, which `keys`, `vals`, `for ... :pairs` and `into` dropped while `count` still counted it. (#3172)
- Treat associative PHP arrays as maps in `merge`, `merge-with`, and `conj`. (#3114)
- Preserve JSON objects as maps during `phel.json/decode`, including empty objects. (#3089)
- Preserve assertion source locations through macro expansion. (#3228)
- Preserve outer `--slowest` and `--last-failed` state across nested test runs. (#3206)
- Avoid redundant project walks and cache races in `phel test --parallel`. (#3203)
- Invalidate the scan-index cache when a configured source or test directory appears. (#3205)
- Load dependencies for test and benchmark files outside configured source directories. (#3187)
- Skip the build output directory when indexing, so navigation prefers a source over its copy in `out/`. (#3155)
- Format collection literals in linear time with byte-identical output. (#3218)
- Replay deprecation warnings from warm compiled-code caches. (#3222)
- Reject blank AI provider API keys before making a request. (#3204)

## [0.50.0](https://github.com/phel-lang/phel-lang/compare/v0.49.0...v0.50.0) - 2026-08-14

### Added

- `Phel\Lang\DynamicScope::$boundNames`, the per-name gate that replaces the `$anyActive` fast path on the global-read path. `$anyActive` is unchanged and still latches (#3179)
- Add LSP go-to-definition for lexical locals: a usage jumps to the symbol that bound it in `let`, `loop`, `fn`, `foreach`, `catch`, `if-let`, `when-let`, `if-some`, `when-some` or `when-first`, honouring shadowing, destructuring and progressive binding scope. A name rebound by a form whose shape is not modelled (`defn`, `for`, `dofor`, `binding`) resolves to nothing rather than to an outer binding of the same name (#3131)
- Add `phel balance [paths]... [--fix]`: reports unbalanced `()`, `[]` and `{}`, and with `--fix` appends the missing closers. Scans the lexer's token stream, so `\(`, a `(` inside a string, a `;` comment or a `#"regex"` is never counted. Detection is the default, and it refuses anything with more than one plausible fix: a surplus or mismatched closer, an unterminated string, a file that will not lex, a trailing reader prefix or lone `\`, or a missing closer followed by a new top-level form, which is read from a column-0 opener, a reader prefix that started at column 0, or a definition head at any indent (#2827 #3164)
- Add `phel.bench` and the `phel bench` command: `defbench` benchmarks written in Phel, with baseline storage (`--store`), comparison (`--ref`) and a tolerance gate (`--tolerance`) (#3005)
- Add Clojure-style PHP interop for value members, dynamic calls, enum cases and `set!`, with editor completion and hover, including static properties (#2881 #2883 #2887 #2888 #2907 #2916)
- Add editor completion and hover for PHP superglobals (`php/$_SERVER`, ...), in the LSP and in the REPL/nREPL (#3037)
- Add `ProjectIndex::$namespaceLocations` and `ProjectIndex::namespaceLocation()`: the `ns` declaration site of each indexed namespace, keyed by its canonical dotted name. `phel index --output` gains a matching `namespaceLocations` key (#3059)
- Add `Phel::installExceptionHandler($projectRootDir)` for source-mapped uncaught throwable reports (#2922)
- Add `.nrepl-port` discovery and graceful shutdown to `phel nrepl` (#2894)
- Add `BuildFacade::enterDependencyLoad()`, `leaveDependencyLoad()` and `isLoadingDependencies()`, plus `BuildConstants::LOADING_DEPENDENCIES`. Additive: they mark the region in which an `ns` form loads its requires, so the emitter can decline to pin call sites there (#3015)
- Add `Phel\Lang\Seq::reductions()` and `Phel\Lang\Seq::flatten()`, the generators backing `phel.core/reductions` and `phel.core/flatten` (#2973)
- Add public `Phel\Shared\NoColor` helpers for embedder output styling (#2923)
- Allow keyword keys in `#php` map literals (#2952)
- Add call-site warnings for definitions marked with `:deprecated`
- Add lint rules `phel/comment-style` and `phel/duplicate-def`
- Add `phel.test/with-isolated-stats` and `with-isolated-reporters`
- Add `phel agent-install --check` for installed `.agents/` drift checks, and a heredoc example to `phel eval -h`
- Add language surface, stability, migration and ADR documentation with drift gates, plus public PHP/core API snapshots, `:doc` checks and `@internal` enforcement
- Add macOS support, coverage, benchmark and mutation gates to CI

### Fixed

- LSP navigation no longer lands in the build output directory. The project indexer walked the whole workspace root, and a project that has been built holds copies of its own `.phel` sources under `out/`, so jumping to a definition could open the compiled copy instead of `src/` or `vendor/`. The excluded directory is read from the project's `PhelConfig`, so a custom `destDir` is honoured; `vendor` is deliberately still indexed, since navigating into the core library is the point (#3155)
- A map or set holding a `nil` key iterated one entry short of its own `count`. The hash map keeps the `nil` key outside the trie, because `nil` has no hash path, and its iterator walked only the trie, so `keys`, `vals`, `for ... :pairs` and `into` all silently dropped that entry while `count` and `get` still reported it. Only maps large enough to use the hash representation were affected (#3172)
- LSP rename no longer corrupts source. `SymbolExtractor` wrote 0-based columns into `Location` and `Definition`, whose contract is 1-based, so every range landed one column left and renaming `add` in `(defn add [x] x)` produced `(defnsubd [x] x)`. The definition site was also edited twice. Go-to-definition on a `def`, document and workspace symbols and find-references shift with it, as do the columns of `phel index --output` and the `api-daemon` responses, which serialize without the LSP conversion (#3157)
- `Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException` extends SPL's `OutOfBoundsException`. `phel.core/nth` raises `\OutOfBoundsException` from its own body, but a `nth` on a statically typed collection lowers to `->get()`, which raises the former; the two shared no ancestor but `Exception`, so `(catch \OutOfBoundsException e ...)` caught the call or not depending on whether the binding happened to carry a tag. Additive (#3042)
- Stop the `-O2` call inliner relocating an argument that contains a PHP interop call, which may write through a by-reference parameter that the inlined closure then captures by value: `phel.http/uri-from-string` silently mis-parsed an IPv6 URI. Stop it splicing away a callee whose parameters carry a `:tag`, which dropped both the emitted parameter type and the native-arithmetic lowering it enables (#3126)
- `phel.html` renders a `raw-string` holding a non-string with Phel's spelling again. The runtime render joins with `php/implode`, which casts with PHP's rules, so `(raw-string true)` came out as `1` and `(raw-string false)` as the empty string. Only the runtime path was affected, not a literal folded by the `html` macro (#3099)
- Print a lazy sequence as `(1 2)` rather than `@[1 2]`. `@` is the deref reader macro, so the old form did not round-trip. Matches `Cons`, `PersistentList` and Clojure (#3113)
- `empty?` no longer reports a lazy sequence whose first element is `nil` as empty, the emptiness check no longer realizes the whole sequence (an unbounded source terminates, and taking 5 of a 200,000 element `lazy-seq` drops from 21ms to 0.004ms), and a `lazy-seq` over a chunked sequence no longer yields only its first element while `count` reported the true length (#3020 #3023 #3025)
- Three architecture tests located Gacela pillars with a glob rooted at the module directory, while Gacela 2.0 resolves them by filename suffix at any depth; all three now walk recursively (#3062)
- `rector.php` selects its PHPUnit rule set with `PHPUnitSetList::COMPOSER_BASED` instead of the pinned `PHPUNIT_100`, which is absent from some `rector-phpunit` builds and aborted `composer test-quality` with a fatal `Undefined constant`. A unit test now asserts every set constant named in `rector.php` exists (#3133)
- Drop `log2` from the compiler's `php/*` return-type table; PHP has no such function, so the entry described a call that could never succeed (#2712)
- Document `~` and `~@` as the unquote and unquote-splicing shorthands in `phel doc`, not `,` and `,@`. Since #2827 `,` is whitespace, so the documented example evaluated to something other than what the same entry claimed
- Report the pattern by name when `phel.string/split` is given something PCRE rejects, instead of letting `preg_split` return false into `Phel/vector` and surfacing a `TypeError` about a bool
- `phel.string/blank?` no longer reports invalid UTF-8 as blank, `trim-newline` no longer rewrites invalid UTF-8 bytes to `?`, and `(join ", " nil)` returns `""` rather than the separator (#3050 #3052)
- Fix literal matching in `phel.string/replace` and `replace-first` (#2957)
- Stop `phel lint` aborting the whole run with an uncaught `TypeError` on a namespace that both defines and calls an `:inline` function (#3055)
- Go-to-definition on a namespace inside a `(:require ...)` form now jumps to that namespace's `ns` declaration, and LSP definition, hover, find-references and rename read a name spelled with the deprecated `\` separator as one word instead of splitting at the backslash (#3059)
- `with-redefs` is no longer ignored after `phel eval` has loaded a required namespace. Loading a dependency runs under build mode, which also let the emitter pin global call sites into `static` slots, and the pinned artifact was written to the ordinary cache for later `phel test` runs to reuse (#3015)
- `phel doc` and the API reference report every arity of a `def` over an `fn`; 23 symbols published no signature at all, `defn` and `defmacro` among them, while `list`, `vector` and `hash-map` published a stale hardcoded one (#3012)
- Stop the scan-index and namespace caches growing without bound on every `phel doc`, LSP hover and REPL completion (#3007)
- Stop internal test, nREPL and watch eval forms from emitting namespace separator deprecations (#2950)
- Validate `slurp` paths correctly while preserving stream-wrapper support, and evaluate `aset` value expressions once (#2941 #2943)
- Resolve `defexception` parent class spellings, `(:use ...)` aliases and dotted class names in `:tag` metadata consistently (#2924 #2936)
- Stop `phel doc` warnings from generated `phel-internal.doc` helpers, and teach dot namespace spelling in `phel doc` for `in-ns`, `ns` and `use` (#2936)
- Honor `NO_COLOR` in exception and REPL error formatting (#2923)
- Reject `$` members outside static-property positions, and compile static property assignment and class-name instance-property places (#2907 #2915)
- Prefer all-caps PHP classes over same-named constants in host symbol resolution
- Generate dot namespaces from `phel init`, examples, templates and `.agents/` recipes, and teach the preferred dotted and markerless spellings across active docs and agent guidance (#2827 #2949)
- Isolate nREPL current namespaces per session and report them in `ns` frames (#2894 #2906)
- Prefer local definitions over same-named `:refer` imports and warn on redefinition (#2897)
- Fail unresolved `(:require ...)` at require time, support project requires in eval/nREPL, and allow symbol redefinition there while keeping file compilation strict (#2886 #2891 #2896)
- Avoid re-evaluating kebab-case namespaces in REPL, nREPL and eval
- Use Phel truthiness in `filter`, `take-while` and `drop-while`
- Preserve metadata through `persistent!` and `assoc`/`conj` specialization
- Support eduction in `first`, `empty?` and `reduce`; keep `count` guidance explicit
- Fix empty iterable `seq`, invalid `lazy-seq` errors, transient sorted-set invocation and `bool|int` sorted comparators
- Stabilize multimethod parameter rendering and generated table docstrings
- Make `phel.repl/compile-str` compile without evaluating
- Report syntax deprecations under `--warn-deprecations` without corrupting generated PHP
- Keep `phel lint` running across project requires, report crashing rules as internal errors, improve handling for loop heads, multi-arity params, dot aliases and deprecated vars, and fail unreadable lint config, source files and directories loudly
- Preserve PHP interpreter flags through OPcache re-exec
- Fix CLI exit codes for format, ns, doc and config commands
- Skip the `inotify` watch backend when `inotifywait` is missing
- Report failing `data-readers.phel` bootstrap files with their cause
- Improve circular dependency, atom validator and chained compiler diagnostics
- Add and verify public core `:example` and `:see-also` metadata
- Amend stale ADRs and document accepted ADR corrections (#2827 #2876)
- Document measured classes loaded by built applications

### Deprecated

- Deprecate source use of `php/new`, `php/->` and `php/::`; prefer Clojure-style interop (#2877)
- Deprecate `set-var`; use `alter-var-root` for roots and `set!` for dynamic binding frames (#2888)

### Changed

- **BC** The core functions listed under Performance declare real arities instead of taking a rest argument. Two consequences. A wrong argument count raises an arity error rather than being silently ignored or reaching a hand-written message, which affects `get`, `get-in`, `reduce`, `into`, `slice`, `range` and the twelve seq functions. And a multi-arity function compiles to one variadic dispatch, so `arity` reports `0` for every one of them, `get`, `assoc`, `dissoc`, `concat`, `swap!`, `swap-vals!`, `range`, `comp`, `repeat`, `repeatedly`, `==`, `merge-with` and `deep-merge` included, as well as `juxt`, `interleave` and `union` (#2960 #2962 #2964 #2975 #3065 #3067 #3069 #3077 #3083)
- BC: `(max)` and `(min)` with no arguments are now rejected at compile time (PHEL002) rather than throwing `ArgumentCountError` at runtime, since both declare their arities (#2991)
- BC: require `symfony/console` `^7.3|^8.0` and `gacela-project/gacela` `^2.0`
- BC: expand `ApiFacadeInterface` and add `FormatterFacadeInterface::formatString`
- BC: move API transfer objects from `Phel\Api\Transfer` to `Phel\Shared\Api`
- BC: rename four `FileIoInterface` contracts after their responsibilities
- Change `Phel\Shared\Api\Definition`, parser/reader contracts and several internal class locations
- Warn for `\` namespace separators without `--warn-deprecations`; other deprecations stay opt-in, and reports for code under `vendor/` are suppressed (#2827)
- Accept the future PHP class spelling after `\`: dotted or bare, with no leading marker. Warn when a `def` shadows a loadable PHP class; `\DateTime` still reaches the class (#2876)
- Lower `(or …)` / `(and …)` test chains containing `php/instanceof`, `php/aget` or a quoted literal to native `||` / `&&` instead of an IIFE; `(get <set> k)` is ~24% faster (#3027)
- Declaring `bool` on the comparison operators lets the compiler infer more return types than before: a `defn` whose body is `(= x 5)` now emits `: bool`, and one branching on `(= x nil)` to two int literals now emits `: int`. More type information, but visible in generated output (#2979)
- Sync `.agents/` incrementally and track installed files in `.agents/.phel-agent-manifest.json` (#2809)
- Adopt explicit Gacela 2 service maps and key facade dependencies by consumer-facing interface
- Route compiler deprecations through one switch, message factory and dedup table

### Performance

Compiler:

- Type a `let`/`loop` binding and a function's return from a collection literal or a `vector`/`list`/`hash-map`/`array-map` call, so the collection specialisations fire on ordinary code rather than only hand-annotated sources: 4.07x on a vector, 2.40x on a map, 3.33x when the collection comes back from a call (#3042)
- Apply every single-use lowering to any typed target, not only a bare variable: `get`, `empty?`, `assoc`/`conj`/`dissoc`, the vector and seq accessors, `reduce`, `get-in`, the named accessors, the numeric predicates and the `php/array` and string gates. `get-in` 2.79x, `nth` 2.42x, `reduce` 1.93x, `get` 1.78x. `last`, `second` and `contains?` still need a bare variable (#3042)
- Carry an inferred tag through a `let` rebinding, into a nested `let`, from an `if`/`do`/nested-`let` init, and from a class or `array` return type (#2985 #2987 #3021 #3040)
- Carry a declared return tag to call sites, so `(str "<" (escape-html s) ">")` lowers to native concatenation: 1.62x on a three-part concatenation, and 26 runtime `str` calls leave the standard library. A `^int` return now takes the native arithmetic path, which overflows to float rather than promoting to `BigInt` (#2712)
- Widen the `php/*` return-type table from 76 entries to 125 across the string, bool and int sides: `(str "[" (php/mb_substr s 0 4) "]")` 1.83x, and `is_a` gives every `defstruct` predicate a `bool` return (#2712)
- Skip the `Truthy` adapter in an `if` whose test is a known PHP bool, and lower `(not x)` over one to a native `!` (3.7x). Covers the comparison operators, any `defn ^bool`, fifteen core predicates and the string interop wrappers: 1 to 7% per test (#2712 #2973 #3021)
- Compile a literal `(list ...)`, `(vector ...)`, `(queue ...)`, `(hash-map ...)` or `(array-map ...)` straight to its `Phel` factory: 2.2 to 3.6x (#3014)

Runtime core:

- Gate the dynamic-scope lookup by name rather than by a single process-wide latch. `Phel::getDefinition` runs on every resolved symbol and is 10.5% of runtime self cost; once anything in a process established a dynamic binding, every global read paid the fiber-local scope check for the rest of the run, 0.153μs against 0.046μs for the registry read alone. A run that binds one var no longer slows reads of every other var: 0.164μs to 0.102μs, and the cost is now the same whether or not a binding exists (#3179)
- Promote a map from the flat array representation to the hash map at 8 entries instead of 16. An array map finds a key by scanning its entries, so a 16-entry map was 4.9x slower to read than a 20-entry one (4.71μs against 0.96μs); reading the last key of a 12-entry map is now 3.5x faster and of a 16-entry map 5.9x. Building is unchanged at both sizes. A map below the threshold still iterates in insertion order (#3172)
- Reverse and escape a string natively in `phel.string`: `reverse` reversed the split array through a Phel sequence and back (`bench_reverse` 8.7x), and `escape` called a closure per character that invoked the character map itself (`bench_escape` 5.8x) (#2973)
- Replace rest-argument dispatch with real arities across `phel.core`: the arithmetic and comparison operators, `str`, the collection constructors and accessors, the seq and transducer functions, and the nil-rejecting arithmetic guards. 1.03x to 25x by how much work sits behind the call, `round` and `floor` 25x, `str` at four and five arguments 3.9x and 4.1x (#2941 #2962 #2964 #2974 #2975 #2978 #2979 #2981 #2989 #2991 #3010 #3017 #3018 #3021 #3065 #3067 #3069)
- Give every closure-returning combinator real arities: `partial` 30x, `fnil` 50x, `constantly` 9.5x, `comp` 8x, `juxt` 4.3 to 5.8x, `complement` 4.4x, `some-fn` 2.3x, `every-pred` 2.2x, plus every transducer's reducing function, `completing` and `transduce` (with `map` 4.8x, with `filter` 3.8x) (#2973 #3021 #3026 #3083)
- **BREAKING** `sort-by` now calls its key function once per element instead of twice per comparison, so an *impure* key function runs far fewer times (100 rather than about 1114 when sorting 100 elements). The sorted result is unchanged. Up to 2.2 times faster; recorded in `docs/spec/clojure-divergences.md` since Clojure calls per comparison (#3006)
- Sort natively rather than calling back into Phel per comparison, when the keys are all native ints or all native strings and no comparator was given: `sort` on strings 22.7x, `sort-by` on strings 13.7x, on ints 8.4x, `(sort-by count strings)` 5.2x. Ties keep input order (#2973 #3003 #3004 #3079)
- Compare two native ints without the numeric tower in `<`, `<=`, `>`, `>=`, `min` and `max` (2.5 to 3.0x), answer `zero?`/`pos?`/`neg?` with a direct PHP comparison (4.2x) and `even?`/`odd?` with one modulo (5.9x) (#2973 #2984)
- Add a PHP array of ints in `sum` with `array_sum`: 27.5x over 200 ints, 17.5x over 20, carrying `mean` 13.9x. `sum` still promotes an overflowing total to BigInt; `mean` divides with `php/fdiv` and has never accepted one (#3085)
- Seed `union`'s result from its first argument instead of re-adding its elements: `(union big small)` 63x, `(union s)` 51x, two 20-element sets 3.0x (#3077)
- Build collections into a PHP array and construct once instead of appending per element: `vec` 3.1x to 3.5x, `into` a vector 3.5x, `php->phel` 1.97x, `kvs` 1.65x, a two-map `merge` 2.6x (#2973)
- Write transient accumulators with `.put`/`.add`/`.find`/`.contains` instead of `get`, `assoc!` and `assoc`: `invert` 2.04x, `group-by` 1.50x, `select-keys` 1.26x, `into` a set 1.23x, `set` 1.19x, `zipmap` 1.15x, `into` a map 1.14x, `frequencies` 1.11x. The `distinct` transducer tracks what it has seen in a transient set: 1.6x (#2973)
- Walk with `foreach` rather than `for ... :pairs`: `stringify-keys` 2.45x, `keywordize-keys` 2.4x, `postwalk` 2.29x, `merge-with` 2.16x, `map-invert` 1.99x, `update-vals` and `update-keys` 1.9x, `rename-keys` 1.76x (#2973)
- Step a generator-backed lazy sequence with one `Cons` per element instead of two `LazySeq` wrappers: `concat` realized 1.62x, `map` realized 1.55x. Realize 4 elements at construction instead of 32, later chunks still 32: `partition-by` 4.8x, `dedupe` 3.5x, at 6-7% for a sequence consumed in full (#2973 #3061)
- Build `reductions` and `flatten` from PHP generators instead of recursing in Phel and walking an eager `tree-seq`: `reductions` realized 2.11x and taken 2.32x, `flatten` constructed 38.0μs to 0.63μs, `(take 3 ...)` 47.6μs to 7.5μs, 64 leaves 120.5μs to 69.3μs. Both stay lazy (#2973)
- Reach the common branch first instead of a chain of predicates in `get` (1.6 to 1.8x, `get-in` 1.2x), `assoc` (1.24x), `dissoc` (1.14x), `conj`, `int`, `empty?` and `seq`, and give `assoc!` a fixed arity (1.74x) (#2960 #2961 #2963 #2965 #2973 #3071)
- Stop `all?` and `some?` re-testing `empty?` once per element: `every?` over a list 2.1x, `not-any?` 1.5x, over a vector 1.4x (#3074)
- Walk `tree-seq`'s stack with native PHP operations and push children by index: 2.1x (#3081)
- Collect `for` results into a PHP array instead of an atom and a persistent `conj` per element, and thread the `:reduce` accumulator through a one-slot PHP array (#2997 #2999)
- Build `to-php-array`'s result with `SeqInterface::toArray` instead of spreading through `apply`: 3.3x at three elements, 8.7x at a hundred, carrying `phel.string/join` 2.2x (#3057)
- Test `reduced?` and the loop guard in `reduce` and `reduce-kv` as the PHP checks they are: `reduce` 1.2x, and `transduce` with `map` a further 1.1x on top of #3101 (#2973)
- Inline PHP array constructors and use `php-indexed-array`, `aget` and `aset` across core and standard libraries, speed up `re-find`, `re-matches` and `parse-long` by avoiding full match-map conversion, and build `interleave`'s seq array in one pass (#2941 #2942 #2943 #2980)
- Walk the entries a hash-map node holds when it splits into an `ArrayNode` instead of probing all 32 slots: 1.06x (#2973)

Standard library:

- Rewrite the hot half of `phel.string`: `assert-string` becomes `:inline`, seven functions take fixed arities, `trim-newline` uses `rtrim`, `blank?` settles ASCII with `strspn` first, `contains?` stops running its guards twice, and `split` builds its vector directly. 1.6x to 8.4x across fourteen functions (#3050 #3051 #3052)
- Render a `phel.html` element without normalizing it first, tag its string helpers `^string`, join the runtime render with `implode`, and hoist `escape-html`'s flag constant (4.4x). A document renders 1.43x, 1.38x and 1.51x faster across the three, and `[:span {:class "x"} "0"]` goes from 17.7μs to 7.0μs (#2973 #3054 #3098)
- Build every `phel.walk` branch through a transient instead of `map` plus `into`: 1.67x to 1.71x (#3087)
- Swap `phel.base64/encode-url` and `decode-url`'s alphabet with one `strtr` instead of chained `replace` calls: 28x and 17x. All three take fixed arities, making `decode` 5.2x (#3021)
- Escape JUnit XML attributes with one `htmlspecialchars` pass instead of five chained `replace` calls: 25x. Invalid UTF-8 now becomes U+FFFD rather than being emitted raw, which was not valid XML (#3094)
- Give `phel.json/encode` and `decode` fixed arities in place of `& [{:flags .. :depth ..}]`: 3.9x encoding a scalar, 1.25x encoding a map, 1.21x decoding (#3090)
- Stop `phel.pprint` printing every node twice and allocating a printer per node: 1.60x on a deep structure, 1.48x on a small one (#3092)
- Derive each `[:map ...]` entry's shape once in `phel.schema/validate` instead of twice: 1.23x on a four-field map, scaling with field count (#3096)

Packaging:

- Keep the PHAR within its release size budget by excluding dependency-only Gacela tooling, the release-announcement scripts and `infection.json5` (#2994)
### Removed

- BC: remove deprecated reader syntax `#| |#`, bare `#`, `|(...)` and `foo$` (#2827)
- BC: remove `^:reference`; use `^:by-ref` (#2827)
- BC: remove long-deprecated core aliases; see the migration guide (#2784)
- BC: remove CLI aliases `phel index --out` and `phel config --json` (#2827)
- BC: remove legacy REPL history migration and `PHEL_QUIET_MIGRATION`
- BC: remove unused `ExceptionPrinterInterface` print methods
- Remove unused facade test-support methods and unused internal helpers

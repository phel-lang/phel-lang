# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Added

#### Reader & Compiler
- `#php` reader literal: `#php [1 2 3]` → `(php-indexed-array 1 2 3)`; `#php {"a" 1}` → `(php-associative-array "a" 1)` (non-recursive)
- PHP interop shorthands: `(.method obj args)`, `(.-field obj)`, `(ClassName/method args)`, `\Ns\Class/MEMBER`, `(new ClassName args)`
- `def` returns a printable var ref (e.g. `#'user/my-var`)

#### REPL
- History vars `*1`, `*2`, `*3`, and `*e` for last exception
- Prompt shows current namespace and tracks `(ns ...)` switches

#### CLI
- `phel eval -` reads the expression from stdin (e.g. `echo '(+ 1 2)' | phel eval -`)
- `phel agent-install [<platform>|--all]` writes skill files for Claude Code, Cursor, Codex, Gemini, Copilot, Aider; `--with-docs` also mirrors the bundled `.agents/` tree, `--dry-run` previews, `--force` skips backup
- `phel nrepl --port=N --host=addr` starts a bencode-over-TCP nREPL server; supports `eval`, `clone`, `close`, `describe`, `load-file`, `interrupt`, plus `completions`, `lookup`, `info`, and `eldoc` for editor tooling
- `phel analyze <file>` prints JSON semantic diagnostics; `phel index <dir>... [--out=file.json]` builds a project symbol table; `phel api-daemon` exposes the Api facade over newline-delimited JSON-RPC on stdio
- `ApiFacade` gains `analyzeSource`, `indexProject`, `resolveSymbol`, `findReferences`, `completeAtPoint` for editor and linter tooling
- `phel lint [paths]... [--format=human|json|github] [--config=path] [--no-cache]` runs read-only semantic rules: unresolved-symbol, arity-mismatch, unused-binding, unused-require, unused-import, shadowed-binding, redundant-do, duplicate-key, invalid-destructuring, discouraged-var; configurable via `phel-lint.phel` with per-rule severity and glob opt-outs
- `phel watch [paths]... [-b backend] [--poll=500] [--debounce=100]` watches `.phel` files and reloads changed namespaces in dependency order; inotify, fswatch, or polling backend auto-picked; `(watch! ["src/"])` in REPL via `phel\watch`
- `phel lsp` starts a Language Server Protocol v3.17 server over stdio (JSON-RPC 2.0, Content-Length framing) with hover, definition, references, completion, document/workspace symbols, rename, formatting, and debounced publishDiagnostics for any LSP-native editor

#### Agent docs
- `.agents/` ships agent-agnostic docs with task recipes, per-platform adapters, and three runnable example projects (`todo-app`, `http-json-api`, `cli-wordcount`)
- `composer test-agents` validates every example against the current source; CI runs it on every PR

#### Formatter
- Aligns key/value pairs in `cond`, `case`, `condp`, and bindings of `let`/`loop`/`binding`/`for`/`foreach`/`dofor`/`if-let`/`when-let`

#### Testing
- `phel\test/report` is a multimethod dispatching on event `:type`; extend with `defmethod report :custom [event] ...`
- Built-in reporters: `default`, `testdox`, `dot`, `tap`, `junit-xml`; select via `phel test --reporter=<name>` (repeatable); `--output=path` writes the junit-xml reporter to a file
- `phel test` metadata-based selectors: `--include=<tag>`, `--exclude=<tag>`, `--ns=<glob>`, `--filter=<regex>` (all repeatable); tag tests via `^:integration` or `^{:tags [:integration :slow]}`; skipped tests emit a `:skipped` event and appear in the summary count
- `defspec` shrinks failing counterexamples via rose-tree-backed `phel\test\shrink` and emits a `:defspec-failed` reporter event with `:shrunk-args`, `:original-args`, `:shrink-steps`, and `:seed`; `^:no-shrink` metadata or `:shrink? false` in the options opts out

#### Modules
- `phel\test\gen`: generators, `sample`, `quick-check`, `defspec` with seedable PRNG
- `phel\ai`: `chat-with-tools` OpenAI tool use, `tool-calls` extraction, `tool-result` helper; retry w/ exponential backoff on 429/5xx; per-call `opts` (`:provider`, `:timeout`, `:base-url`, `:api-key`, `:max-retries`); `*http-post*` seam; `docs/ai-guide.md`
- `phel\core`: `uuid=`, `uuid-nil?`, `uuid-version`, `uuid-variant`
- `phel\core`: `defmulti` accepts an optional docstring: `(defmulti name "doc" dispatch-fn)`; non-callable string dispatch-fn raises a clear `InvalidArgumentException` instead of a raw PHP "undefined function" error
- `phel\repl`: `find-ns`, `create-ns`, `remove-ns`, `intern`, `ns-interns`
- `phel\cli`: spec-map wrapper over `symfony/console` with prompts, tables, progress, coercion, hooks, signals, and test helpers. See `docs/cli-guide.md`
- `phel\match`: `match` macro with literal, vector, map, wildcard, `:as`, `:guard`, `:or`, and rest-binding patterns; matches left-to-right and raises on no-match when no `:else` is given
- `phel\schema`: data-driven schemas with `validate`, `explain`, `conform`, `coerce`, `generate`, `instrument!`; supports scalar kinds, `:vector`, `:set`, `:map`, `:map-of`, `:tuple`, `:enum`, `:and`, `:or`, `:maybe`, `:re`, `:fn`, `:ref`, and `[:=> args ret]` function schemas with named-schema registry
- `phel\async`: fiber-backed `promise`, `deliver`, `future-call`, `future-fiber`, and `future?`; cooperative scheduler works at the top level with 3-arg `deref` timeouts
- Async guide (docs/async-guide.md) and expanded phel\async docstrings.
- `phel doc` and REPL completion now cover `phel\async`, `phel\cli`, `phel\match`, `phel\pprint`, `phel\router`, `phel\walk`, and `phel\test\gen`

### Fixed

#### REPL & Compiler
- `eval()` runtime errors point to user's `string:N` line via source map
- Non-callable literal calls (`('foo)`, `(42)`, `(nil)`, `("x")`) raise `PHEL011` at analysis time with source location
- REPL multi-line buffer: `#(...)`, `|(...)`, `#?(...)`, `#?@(...)`, brackets `[]`, braces `{}`, and `#{...}` sets now count toward balance; no more premature eval of unclosed forms

#### Build
- `phel build` no longer leaks compiled-program stdout during compilation
- Windows cache: absolute paths with drive letters or UNC prefixes no longer prefixed with app root

#### Modules
- `phel\ai` `check-response` raises `RuntimeException` with provider message when body lacks `:error :message`
- `phel\ai` text extraction picks first `text` block, skipping preceding `tool_use`
- `phel\http/request-from-globals` error explains that an HTTP request context is required and points to `request-from-map` for tests
- `(:key nil)` returns the default instead of raising `TypeError`
- `(get v nil)` and `(get l nil)` on vectors/lists return the default instead of raising `TypeError`
- Vector/list called with nil index raise `InvalidArgumentException` with a clear message instead of a raw PHP `TypeError`
- Stack-trace arg rendering truncates each Phel argument at 200 chars

### Changed

- `docs/php-interop.md`: namespaced PHP functions (`php/Amp\trapSignal`) and `(def alias php/\Ns\fn)` capture
- `phel build` prints summary with fresh/cached counts and output directory
- `phel\ai` `chat-with-tools` returns `{:text :tool-calls :stop-reason :raw}`

### Removed

#### Public API
- `phel\http/create-response-from-map`, `phel\http/create-response-from-string` (use `response-from-map` / `response-from-string`)
- `Keyword::createForNamespace()` (use `Keyword::create($name, $namespace)`)
- `PhelConfig::setOut()` (use `PhelConfig::setBuildConfig()`)
- `PhelBuildConfig::setMainPhpFilename()` (use `PhelBuildConfig::setMainPhpPath()`)
- `PhelFunction` accessors `name()`, `doc()`, `fnSignatures()`, `signature()`, `description()`, `groupKey()`, `githubUrl()`, `docUrl()`, `file()`, `line()`, `namespace()` (use readonly properties)

#### Internals
- `GlobalEnvironmentNotInitializedException`, `PhelFileFinder`, `PhelFileFinderInterface`
- `FileException::canNotCreateTempFile()`, `ExtractorException::duplicateNamespace()`, `EmitterResult::getSource()`, `TokenStream::getReadTokens()`, `LoadClasspath::resetCache()`

### Changed (breaking)

- `phel init` defaults to Flat layout (`src/`, `tests/`); `--nested` keeps `src/phel/`
- `ProjectLayout::Conventional` → `ProjectLayout::Nested`; `useConventionalLayout()` → `useNestedLayout()`

## [0.33.0](https://github.com/phel-lang/phel-lang/compare/v0.32.0...v0.33.0) - 2026-04-17

### Added

#### Tooling & CLI
- `cache:warm` command
- `debug:container`, `debug:dependencies`, `debug:modules`, `list:modules`, `profile:report`, `validate:config` commands
- `build/preload.php` opcache preload script
- `phel doctor` Build health check and Gacela module health checks

#### Build & Caching
- `ScopedCache` for dependency-aware cache invalidation
- `#[Cacheable]` on directory lookups and namespace encoding
- `cache:clear` clears Gacela class-name and merged-config caches

#### Testing
- `ContainerFixture` trait resets Gacela container between tests
- `use-fixtures` for `:each` / `:once` fixtures (#1439)

#### Reader & Compiler
- `(use ClassName [:as Alias] ...)` top-level form
- `(ClassName. args)` constructor shorthand (#1359)
- `#uuid "…"` tagged literal (#1376)
- Ratio literals `N/M`; `1/0` → `INF`, `0/0` → `NaN`
- `:syms` map destructuring

#### Predicates
- `ident?`, `simple-ident?`, `simple-keyword?`, `simple-symbol?` (#1369, #1381)
- `special-symbol?` (#1384), `ifn?` (#1370)
- `neg-int?`, `pos-int?`, `nat-int?` (#1374)
- `sequential?` (#1380), `seqable?` (#1379)
- `any?`, `ratio?`, `instance?` (#1433)

#### Sequences & Collections
- `nth`, `nthrest`, `nthnext` (#1375)
- `fnext` (#1368)
- `rseq`, `reversible?` (#1378)
- `empty` (#1365)
- `key`, `val` (#1372)
- `(keyword ns name)` arity (#1428)
- `mapcat` multi-collection arity (#1428)
- Transients callable like persistents (#1428)

#### PHP Interop
- `aget`, `aset` with nested index support (#1356)
- `int-array`, `long-array`, `float-array`, `double-array`, `short-array` (#1382)
- `int`, `long`, `short` coercion (#1371, #1383)
- `uuid?`, `parse-uuid` (#1377)
- `alter-var-root` stub (out of scope) (#1357)
- `parse-double` accepts `Infinity`, `-Infinity`, `NaN` (#1428)
- `alength` (#1433)

#### Observability
- `tap>`, `add-tap`, `remove-tap`; synchronous dispatch, swallows tap exceptions

#### Modules
- `phel\router`: data-driven router on `symfony/routing`; `routes`, `:route-name`, per-case error handlers
- `phel\http-client`: outbound HTTP over PHP streams
- `phel\ai`: chat, completions, structured extraction, tool use, embeddings, semantic search
- `phel\repl` AI helpers: `explain`, `suggest`, `fix`, `review`, `embed-ns`, `search-ns`

### Fixed

- `CompiledCodeCache` keys by source file path (cache version 1.2)
- `keyword` idempotent; handles `nil` / symbol (#1428)
- `dissoc` accepts zero keys and variadic (#1428)
- `keys` / `vals` return `nil` for `nil` or empty (#1428)
- `make-hierarchy` exposes `:parents`, `:descendants`, `:ancestors` (#1428)
- `first` on map returns its first entry (#1428)
- `str` preserves float representation; readable `NaN` / `±Infinity` (#1428)
- `compare` throws on cross-category; `nil` less than non-nil (#1428)
- `:keyword` lookup on transient maps (#1428)
- `deftest` rejects missing/non-symbol names (#1364)
- `(def name)` binds `nil` (#1361)
- `doseq` pair bindings without `:in` (#1362)
- `doseq` iterates maps as `[k v]` entries (#1433)
- `is` accepts scalar literals (#1433)
- `drop-last` on lazy sequences and ranges (#1360)
- `(empty? (range))` terminates (#1366)
- `is` handles `let` / `when` / `cond` (#1367)
- `defrecord` / `defstruct` / `defexception` / `definterface` valid in statement mode (#1358)
- `defstruct` / `defrecord` / `defexception` / `definterface` nestable in function bodies
- `phel\router` error dispatch: 404/405/406
- Namespace extractors skip build output directory

### Changed

- Upgraded Gacela to ^1.14; `#[Provides]` providers with `getRequired()`
- `Phel::run()` resolves `FilesystemFacade` via `Gacela::getRequired()`
- `composer test-quality` runs `validate:config`
- Dropped `phpunit/php-timer`; internal resource-usage formatter
- **Breaking:** `phel\str` renamed to `phel\string` (#1440)
- `(load path)`: relative from caller file; `/path` classpath-absolute via `phel\repl/src-dirs`; `./`, `../`, `.phel` rejected
- `src/phel/core.phel` split into topic files under `src/phel/core/`
- Phel test files reorganized into `core/`
- `phel\router` caches Symfony matcher/generator and precompiles middleware
- `phel test` skips compile failures; `--fail-fast` aborts

### Removed

- `phel\debug` namespace (`dotrace`, `dbg`, `spy`, `tap`, `reset-trace-state!`, `set-trace-id-padding!`)

### Performance

- Hot type predicates via `php/instanceof` / `php/is_*`: `vector?`, `list?`, `set?`, `keyword?`, `symbol?`, `string?`, `boolean?`, `integer?`, `float?`, `number?`, `php-array?`, `indexed?`, `associative?`, `sequential?`, `coll?`
- `every?` / `all?` use `empty?` (O(1) on lazy seqs)
- `select-keys` O(|ks|)
- `into` transient fast-path
- Transient accumulators in `set`, `vec`, `frequencies`, `merge`, `merge-with`, `select-keys`, `rename-keys`, `update-keys`, `update-vals`, `invert`, `group-by`
- `reverse`, `sort`, `sort-by`, `shuffle`, `doall`, string `next` / `rest` / `seq` build vectors from PHP array directly
- `zipcoll` delegates to `zipmap`
- Removed shadowed eager `interleave` / `interpose`

## [0.32.0](https://github.com/phel-lang/phel-lang/compare/v0.31.0...v0.32.0) - 2026-04-12

### Added

#### Async & Concurrency
- `phel\async` module with `async`, `await`, `delay`, `await-all`, `await-any`, `->closure` for AMPHP-based concurrency (#793)
- `pmap` for fiber-based parallel map (IO-parallel via AMPHP), matching Clojure's `pmap` (#793)
- `amphp/amp` promoted to a runtime dependency so `phel\async` works out of the box, including from the PHAR (#793)
- `future` macro returning a `PhelFuture` compatible with `deref`, `realized?`, and the `@` reader shortcut; `await`/`await-all`/`await-any` accept both `PhelFuture` wrappers and raw `Amp\Future` values (#1287)
- 3-arg `(deref future timeout-ms timeout-val)` for time-bounded blocking on a `PhelFuture` (#1313)
- `future-cancel`, `future-cancelled?`, `future-done?` for `PhelFuture` lifecycle management (#1313)

#### Reader & Compiler
- Character literals: `\a`, `\space`, `\newline`, `\tab`, `\uNNNN`, `\oNNN`, and punctuation forms; compile to single-char PHP strings. FQN parsing is preserved via lookahead (#1283)
- Var-quote reader syntax `#'foo`, read as the bare symbol `foo` since Phel has no first-class Var type (#1317)
- Symbolic number literals `##Inf`, `##-Inf`, `##NaN` for `.cljc` interop (#1276)
- Generic `#<tag>` tagged-literal dispatch (`#cpp`, `#uuid`, `#inst`, …) read as `TaggedLiteralNode`; unknown tags in unselected `#?` branches parse without error (#1277)
- `fn` accepts an optional name symbol for self-recursion: `(fn my-name [x] ...)`, including multi-arity forms (#1279, #1299)
- Radix literals `NrXXX` (bases 2–36), e.g. `2r1111`, `16rFF` (#1281)
- `N`/`M`-suffixed numeric literals (`1N`, `1.5M`); suffix is stripped since Phel has no BigInt/BigDecimal, accepted for `.cljc` compat (#1325)
- Dot namespace separator and Clojure aliasing for FQNs, enabling `phel.core/fn`, `clojure.core/fn` (#1251)
- Accept `.` as alternate namespace separator in `ns`, `in-ns`, `:require`, `:use` (#1177)
- Vector entries in `:require`, e.g. `(:require [phel\str :as s :refer [upper-case]])` (#1183)
- Automatic `clojure.*` → `phel.*` namespace remapping in `:require` when target exists (#1207, #1210)
- `~` and `~@` reader macros for `unquote`/`unquote-splicing`, alongside existing `,`/`,@` (#1201)
- `name#` auto-gensym suffix inside syntax-quote, alongside existing `name$` (#1195)
- `&form` and `&env` implicit symbols in every `defmacro` body, enabling dialect detection via `(:ns &env)` (#1185)

#### Core Language
- `defrecord` and `deftype` macros expanding to a `defstruct` plus `->Name` factory (`defrecord` also generates `map->Name`); optional protocol tail is spliced into `extend-type` (#1324)
- `reify` for anonymous protocol/interface implementation (#1226)
- `sorted-map`, `sorted-map-by`, `sorted-set`, `sorted-set-by` for sorted persistent collections (#1228)
- `sorted?` predicate for sorted-map/sorted-set (#1274)
- 0-arg `(range)` returns an infinite lazy sequence starting at 0 (#1259)
- `letfn` macro for mutually recursive local functions (#1224)
- `condp` macro for predicate-based dispatch, including `:>>` result threading (#1217)
- `if-some`, `when-some`, `when-first` macros for nil-aware binding (#1218)
- `assert` macro for precondition checking with optional custom message (#1222)
- `*assert*` var (default `true`) read by `assert` at macroexpand time; when false, `assert` expands to `nil` (#1315)
- Nested `def`/`defn` inside another `def`/`defn` body is now permitted (#1316)
- `dotimes` macro and `run!` function for side-effecting iteration (#1252)
- `fnil` for nil-safe function wrapping with default values (#1225)
- `vary-meta` for applying a function to an object's metadata (#1223)
- `min-key`, `max-key` for finding extremes by a derived value (#1221)
- `rename-keys` for renaming map keys via a key map (#1220)
- `seq?` predicate for `LazySeqInterface` (#1231)
- `(boolean x)` coercion: `false` for `nil`/`false`, `true` otherwise (#1186)
- 1-arg `(some? x)` form alongside the existing 2-arg `(some? pred coll)` (#1184)
- `(resolve sym)` globally available in `phel\core` (no longer requires `phel\repl`) (#1187)
- `:or` defaults and `:strs` string-key support in map destructuring (#1219, #1227)
- `disj` variadic function for removing keys from sets (#1285)
- `float` and `double` coercion functions returning a PHP float (#1282)
- `object-array` returning a plain PHP indexed array accessible via `php/aget`/`php/aset` (#1318)
- `array-map`, aliased to `hash-map` since Phel has no distinct array-map type (#1319)
- `to-array` for converting any collection to a plain PHP array (#1320)
- `aclone` producing a shallow independent copy of a PHP array (#1321)
- `byte` coercion truncating to a signed 8-bit range (`-128..127`) (#1327)
- `char` coercion returning a single-character string from a code point or 1-char string (#1330)
- `char?` predicate (single-char string, UTF-8 counted), matching ClojureScript semantics (#1334)
- `coll?` predicate for persistent collections (vectors, lists, hash-maps, structs, sets, lazy-seqs) (#1336)
- `conj!` transient mutator with Clojure-compatible arities over transient vectors, sets, and maps (#1338)
- `assoc!`, `dissoc!`, `disj!`, `pop!` transient mutators, throwing `InvalidArgumentException` on persistent or mismatched targets (#1353)
- `some-fn` higher-order predicate combinator, short-circuiting on first logical-true result (#1339)
- `counted?` predicate for collections with constant-time length (lazy-seqs excluded) (#1340)
- Single-arity `(drop-last coll)` equivalent to `(drop-last 1 coll)` (#1343)
- `NaN?` as an alias for `nan?` (#1284)

#### Clojure-Compatible Aliases
- `atom`, `atom?`, `reset!` as aliases for `var`, `var?`, `set!` (#1252)
- `persistent!` as alias for `persistent` (#1353)
- `double?` as alias for `float?` (#1353)
- `identical?` as alias for `id` (#1252)
- `fn?` as alias for `function?` (#1252)
- `map?` as alias for `hash-map?` (#1252)
- `vals` as alias for `values` (#1252)
- `integer?` as alias for `int?` (#1199)
- `with-meta` made public as replacement for `set-meta!` (#1252)

#### Testing
- `are` macro in `phel\test` for template-based multiple assertions (#1255)
- `testing` macro in `phel\test` for grouping assertions with context strings (#1237)
- `do-report` in `phel\test` for custom assertion reporting (#1260)
- `phel\test/assert-expr` is now an open multimethod, letting users extend `is` with custom assertion forms (#1188)
- `defmethod` preserves the namespace of the multimethod, enabling cross-namespace extension with fully qualified multi-names

#### REPL & Tooling
- `require` and `use` accept quoted symbols in the REPL (e.g. `(require 'phel\str)`) for nREPL compatibility (#1211)
- `ProjectLayout::Root` for single-file / scratch projects (`srcDirs = ['.']`), and `PhelConfig::forProject(layout: …)` named-argument style with auto-detected layout when called with zero arguments (#1355)
- `phel init --minimal` generates a root-layout project: single `main.phel` + matching `main_test.phel` + one-line `phel-config.php` at the repo root (#1355)
- `phel init` now generates a matching test file by default (opt out with `--no-tests`) and lists `phel test` in the next-steps output (#1355)

#### Documentation
- Clojure-to-Phel migration guide covering naming, interop, namespaces, and feature differences (#1229)
- README "Getting Started" section showing `composer require` + `phel init` flow
- Quickstart tutorial replaced hand-written `phel-config.php` with `phel init`; kept the manual form as an appendix

### Changed
- Anonymous `fn` emits native PHP closures instead of `AbstractFn` subclasses, making them compatible with libraries that type-hint `\Closure` (e.g. AMPHP) without `->closure` conversion (#793)
- `QuoteNode` preserves the original reader-macro prefix (`,` vs `~`, `'`, `` ` ``, `@`) for faithful parser/printer round-trip (#1203)
- Split `Phel\Lang\Generators\SequenceGenerator` into focused sibling generators (`TransformGenerator`, `SliceGenerator`, `CombineGenerator`, `DedupeGenerator`) (#1197)
- Migrate all Phel source and test files from `|(...)` to `#(...)` short function syntax (#1179)
- Migrate all in-repo Phel source, tests, and docs from `,`/`,@` to `~`/`~@` for `unquote`/`unquote-splicing` (#1203)
- Migrate `time` macro in `phel\core` from `name$` to `name#` auto-gensym suffix (#1203)
- Migrate internal `tests/phel/` usage of `#|...|#` multiline comments to `;;` line comments (#1276)

### Deprecated
- `#|...|#` multiline comments and bare `#` line comments (deprecated since v0.30, now announced for removal in v0.33); use `;;` and `#_` instead (#1276)
- `var` → `atom`, `var?` → `atom?`, `set!` → `reset!` (#1252)
- `id` → `identical?` (#1252)
- `function?` → `fn?`, `hash-map?` → `map?` (#1252)
- `values` → `vals` (#1252)
- `set-meta!` → `with-meta` (#1252)
- `|(...)` short function syntax with `$` placeholders; use `#(...)` with `%` instead (#1179)
- `,`/`,@` as `unquote`/`unquote-splicing` reader macros; use `~`/`~@` instead (#1203)
- `name$` auto-gensym suffix; use `name#` instead (#1203)

### Fixed
- `(meta x)` on a local binding returns the value's metadata; only `(meta 'sym)` looks up definition metadata (#1352)
- `(assoc nil k v)` returns a fresh map, matching Clojure (#1352)
- `assoc` on a non-associative value throws `InvalidArgumentException` naming the received type (#1352)
- `abs` throws `InvalidArgumentException` on non-numeric input instead of silently returning `0` (#1352)
- `drop-last` no longer errors on `nil`: `(drop-last n nil)` and `(drop-last nil)` return `[]`, aligning with `drop`/`take` (#1344)
- `reset!`, `swap!`, `set!` now return the newly-stored value instead of `nil`, matching Clojure (#1304)
- `associative?` returns `true` for vectors and PHP indexed arrays, matching Clojure's `Associative` protocol (#1303)
- `(vec map)` returns entries as 2-element vectors (e.g. `(vec {:a 1}) => [[:a 1]]`) instead of just values (#1305)
- `min-key`/`max-key` return the latest argument on ties, matching Clojure's reference implementation (#1306)
- 1-arg `(thrown? body)` defaults to catching any `\Throwable`, matching Clojure's portability convention (#1307)
- `-0x8000000000000000` (and `-0b...`, `-0o...` at 64-bit minimum) parses correctly to `PHP_INT_MIN` instead of crashing with `ParseError` (#1278)
- `are` macro no longer eagerly evaluates list literals in table cells; cells are substituted symbolically, matching `clojure.template/do-template` (#1280)
- `=` on lazy sequences no longer realizes an infinite side: `LazySeq`/`ChunkedSeq`/`AbstractPersistentVector` short-circuit on identity and walk pairwise via lockstep iterators (#1286)
- `(php/yield ...)` in return position no longer emits `return yield ...;`, which broke PHP generator semantics (#793)
- `phel run` no longer buffers output so `println`/`print` flush immediately, fixing silent output in long-running AMPHP servers (#793)
- REPL `require` supports dot namespace separator and Clojure aliasing, e.g. `(require phel.str)`, `(require clojure.str)` (#1263)
- REPL `(require 'foo)` throws `RuntimeException` when the namespace cannot be found instead of silently succeeding (#1246)
- PHP reserved keywords (e.g. `and`, `list`, `class`) allowed in namespace names, matching PHP 8.0+ (#1230)
- `macroexpand`/`macroexpand-1` are now functions (were macros), so quoted forms expand correctly and unquoted forms evaluate eagerly (#1209)
- `macroexpand` no longer applies inline expansion to non-macro forms: `(macroexpand '(+ 1 2))` returns `(+ 1 2)` (#1208)
- Lexer no longer swallows a reader conditional (`#?(...)`) following a gensym-suffixed symbol (#1195)
- Lexer accepts `'` inside and at the end of symbol names (e.g. `a'`, `foo''`, `a'b`); leading `'` is still the quote reader macro (#1275)
- `php/...` calls to namespaced PHP functions (e.g. `php/Amp\File\write`) emit fully qualified names so they resolve from compiled/cached files (#1180)
- `phel.phar` no longer emits duplicate-namespace warnings or fails to write the compiled-code cache when run from a directory without `phel-config.php` (#1354)

## [0.31.0](https://github.com/phel-lang/phel-lang/compare/v0.30.0...v0.31.0) - 2026-04-03

### Added

#### Reader & Compiler
- `#?()` reader conditionals and `#?@()` reader conditional splicing with `:phel`/`:default` platform keys, plus `.cljc` file support (#1171)
- `#"..."` regex literal syntax as reader sugar for PCRE patterns (#1153)
- `re-find`, `re-matches` regex functions (#1153)
- `@` reader syntax as shorthand for `(deref ...)` (#1164)
- `#(...)` anonymous function shorthand with `%`, `%1`, `%2`, `%&` parameter placeholders (#1146)
- Deprecation warnings for `#` line comments and `#| |#` multiline comments (#1146)
- Store parameter names as `:arglists` in function metadata during compilation (#1127)

#### Core Language
- Protocol system: `defprotocol`, `extend-type`, `extend-protocol`, `satisfies?`, `extends?` (#1151)
- Hierarchy system: `derive`, `underive`, `isa?`, `parents`, `ancestors`, `descendants`, `make-hierarchy` with hierarchy-aware multimethod dispatch (#1156)
- Transducer system: `transduce`, `into` (3-arg), `sequence`, `completing`, `cat`, plus transducer arities for `map`, `filter`, `remove`, `take`, `drop`, `take-while`, `drop-while`, `take-nth`, `keep`, `keep-indexed`, `distinct`, `dedupe`, `mapcat`, `interpose` (#1152)
- `ex-info`, `ex-data`, `ex-message`, `ex-cause` for structured exceptions with data maps (#1149)
- `delay`, `force`, `delay?` for deferred cached computation (#1155)
- `add-watch`, `remove-watch`, `set-validator!`, `get-validator` for atom observation (#1154)
- `iteration` function for consuming paginated/cursor-based APIs as lazy sequences (#1157)
- `update-keys`, `update-vals`, `parse-long`, `parse-double`, `parse-boolean`, `abs`, `inf?`, `random-uuid` utility functions (#1150)

#### REPL & Tooling
- `source`, `find-fn`, `symbol-info` introspection functions (#1128, #1132, #1125)
- `ns-publics`, `ns-aliases`, `ns-refers`, `ns-list` for namespace introspection (#1131, #1134)
- `macroexpand-1`, `macroexpand` macros and `CompilerFacade` methods (#1138)
- `eval-str`, `eval-capturing`, `load-file` evaluation functions (#1136, #1135, #1124)
- `test-ns` for running namespace tests interactively; `reset-stats`, `get-stats`, `restore-stats` for test management (#1133)
- GlobalEnvironment snapshot/restore to rollback state on eval errors (#1129)
- Structured stack frames in `EvalError` for nREPL stacktrace support (#1130)
- Typed completion results and alias/referred-symbol completion in `ReplCompleter` (#1137, #1121)
- Stdout capture in `EvalResult`, `structuredEval()` on `RunFacade` for external tooling (#1123, #1121)
- Auto-inject REPL utilities (`doc`, `require`, `use`) on `(in-ns ...)` (#1121)

### Changed
- Standardize comment syntax: replace `#` with `;` and `;;` for standalone comments across all Phel source files (#1140)

### Fixed
- `.cljc` files not discovered by `phel run` and `phel ns` due to missing extension in cached namespace extractor (#1176)
- `phel --help` showing only REPL help instead of all commands (#1141)
- `(str false)` and `(str true)` now return `"false"` and `"true"` (Clojure semantics) (#1122)
- REPL: `*ns*` preserves hyphens, `(ns ...)` requires work with empty `src-dirs`, runtime `require` works without `loadPhelNamespaces()` (#1120)
- `phel run`: prevent duplicate output on first run (#1142)
- PHAR: deduplicate stdlib source directory, pre-compile all stdlib modules (#1119)
- Cache: resolve compiled paths from current cache dir, not stored paths (#1119)

## [0.30.0](https://github.com/phel-lang/phel-lang/compare/v0.29.0...v0.30.0) - 2026-03-25

### Added
- Add `subset?` predicate for sets: `(subset? (hash-set 1 2) (hash-set 1 2 3))` => `true`
- Add `superset?` predicate for sets: `(superset? (hash-set 1 2 3) (hash-set 1 2))` => `true`
- Add `cond->` macro for conditional thread-first: `(cond-> 1 true inc false (* 42)) ; => 2`
- Add `cond->>` macro for conditional thread-last: `(cond->> [1 2 3] true (map inc)) ; => [2 3 4]`
- Add `vec` function to coerce collections to vectors: `(vec '(1 2 3))` => `[1 2 3]`
- Add `hash-set` function to create sets from arguments (like Clojure's `hash-set`)
- Add `phel\walk` module with `walk`, `postwalk`, `prewalk`, `postwalk-replace`, `prewalk-replace`, `keywordize-keys`, and `stringify-keys`
- Add `phel\pprint` module with `pprint` and `pprint-str` for pretty-printing nested data structures with configurable width
- Add `tap>`, `add-tap`, `remove-tap`, and `reset-taps!` to `phel\debug` for a global tap handler system
- Add `dir`, `apropos`, and `search-doc` to `phel\repl` for namespace exploration and documentation search
- Add `defmulti` and `defmethod` macros for runtime polymorphism via dispatch functions
- Add `--fail-fast` option to `phel test` to stop on first failure or error

### Changed
- Optimize `str/blank?`: single regex pass instead of character-by-character loop
- Optimize `str/escape`: `array_map` + `implode` instead of O(n²) string concatenation
- Optimize `str/last-index-of`: native `mb_strrpos` instead of O(n²) loop
- Optimize `core/reverse`: native `array_reverse` instead of element-by-element `conj`
- Optimize `core/interleave`: `reduce` + `conj` instead of repeated `concat`
- Eliminate redundant iterations in `walk`, `keywordize-keys`, and `stringify-keys`
- Cache `print-value` result in `pprint` to avoid redundant calls
- Extract `as-pattern` helper to deduplicate `str/replace` and `str/replace-first`
- Add identity fast-path (`===`) in `Equalizer::equals()` to skip `instanceof` for identical values
- Optimize `NodeEnvironment::hasLocal()` — direct name comparison instead of `Symbol::create()` + `in_array`
- Optimize `PersistentList::get()` — early bounds check, loop only to target index
- Eliminate double hash lookup in `PersistentHashSet::add()`/`remove()`
- Optimize `PersistentVector::cons()` — use `TransientVector` instead of full array rebuild
- Deduplicate `GlobalEnvironment::getAllDefinitions()` via hash keys instead of `array_unique`
- Optimize `NodeEnvironment::withMergedLocals()` — avoid `array_unique` on Symbol objects
- Use 2-level nested cache in `NodeEmitterFactory` to avoid string concatenation per node
- Cache indentation strings in `OutputEmitter`
- REPL now gracefully falls back to `fgets(STDIN)` when the readline extension is unavailable (Docker, CI)
- Inline truthy checks at emit time — eliminates `Truthy::isTruthy()` static method call overhead on every conditional
- REPL and eval now use in-memory evaluation (`eval()`) instead of writing temp files, significantly reducing I/O overhead and startup time
- `assoc` now accepts multiple key-value pairs in a single call (Clojure alignment): `(assoc m :a 1 :b 2 :c 3)`
- **BREAKING**: `set` now coerces a collection to a set (Clojure alignment): `(set [1 2 3])` => `#{1 2 3}`
- Use `hash-set` for creating sets from arguments: `(hash-set 1 2 3)` => `#{1 2 3}`
- Keywords are now interned (flyweight pattern) — same name/namespace returns the same instance, enabling `===` identity checks and reducing GC pressure

### Fixed
- Restore GlobalEnvironment refers/aliases when loading from compiled code cache
- Functions used in string concatenation (e.g. `(str "Hello, " name "!")`) no longer crash with a PHP error; they now render as `<function:name>`
- Fix `zipmap` causing out-of-memory error when used with infinite lazy sequences (e.g. `(zipmap keys (repeat val))`)
- Fix `peek` crashing when used on lazy sequences (e.g. from `filter` or `map`)
- Fix excessive blank lines in test output between test dots and summary
- Fix REPL catch-all error handler showing raw PHP traces instead of source-mapped Phel stack traces

## [0.29.0](https://github.com/phel-lang/phel-lang/compare/v0.28.0...v0.29.0) - 2026-02-01

### Added
- Add "Did you mean?" suggestions for undefined symbols using Levenshtein distance
- Add error codes (PHEL001-399) for documentation lookup and categorized error types
- Add type hints in error messages with clean type names (e.g., `Symbol` instead of `Phel\Lang\Symbol`)
- Add arity ranges in function call errors (e.g., "Expected: 2 or 3" or "at least 2")
- Add macro expansion context showing the macro name and form being expanded when errors occur
- Add parser error context showing the line where unterminated constructs started

### Changed
- Add emoji headers to GitHub release notes in `release.sh`

### Fixed
- Fix `re-seq` doesn't return capture groups (#1086)

## [0.28.0](https://github.com/phel-lang/phel-lang/compare/v0.27.0...v0.28.0) - 2026-01-18

### Added
- Add `*program*` variable and `Phel::setupRuntimeArgs()`, `getProgram()`, `getArgv()` for runtime argument management
- Add `phel init` command with `--flat`, `--force`, `--dry-run`, `--no-gitignore` options
- Add `PhelConfig` improvements: `forProject()` factory, `ProjectLayout` enum, layout helpers, getters, setters, and `validate()` method
- Add zero-config support with automatic project structure and namespace detection

### Changed
- **Breaking**: `argv` now excludes script/namespace name; use `*program*` instead
- **Breaking**: `Phel::run()` and `Phel::bootstrap()` no longer accept string `$argv`
- **Breaking**: Default directories changed to conventional layout (`src/phel/`, `tests/phel/`)
- **Breaking**: `remove` now uses Clojure semantics `(remove pred coll)`
- Refactor `SequenceGenerator` and `PartitionGenerator` with dependency injection and extracted helpers

### Fixed
- Fix `defexception` macro failing with parse error due to invalid `apply php/new` usage
- Improve docblocks example code on the API page to make it REPL-friendly

## [0.27.0](https://github.com/phel-lang/phel-lang/compare/v0.26.0...v0.27.0) - 2025-12-24

### Added
- Add duplicate namespace detection warning to help diagnose config issues
- Add `compact` function for removing nil values
- Add a persistent namespace cache with mtime invalidation for ~99% faster warm runs
- Add `cache:clear` command for clearing namespace and compiled code caches
- Add compiled code cache for significantly faster test execution
- Add `memoize-lru` function with configurable cache size to prevent unbounded memory growth

### Changed
- Standardize docblock examples with triple backtick code fencing for better IDE rendering and syntax highlighting
- Optimize `:see-also` metadata by using string vectors instead of quoted vectors for better performance
- Improve compiled code cache with version-based invalidation, LRU eviction, and atomic writes
- **Breaking**: `PhelFunction::fromArray()` now requires `signatures` (plural) instead of `signature` (singular) to properly support multi-arity functions
- Multi-arity function signatures are now properly extracted and displayed in documentation (e.g., `csv-seq`, `memoize-lru`, `conj`)

### Fixed
- Load host project's vendor autoloader in PHAR mode for PHP class dependencies
- Fix exceptions being hidden in `phel run` when nested requires have errors (#926)
- Fix notation for arguments in the anonymous function of docblocks examples (#1070)
- Fix `defn-builder` to generate all signatures for multi-arity functions instead of only the first arity

## [0.26.0](https://github.com/phel-lang/phel-lang/compare/v0.25.0...v0.26.0) - 2025-11-16

### Added
- Basic string iteration support
  - Strings work directly in `foreach` loops and with `count`/`frequencies`
  - Sequence functions work directly on strings without explicit conversion
  - Add `seq` function and `phel\str/chars` for explicitly converting strings to character vectors when needed
  - Full UTF-8/multibyte support
- Add a mocking framework in `phel\mock` namespace for test doubles
- Add URL support to `slurp` function (http://, https://, ftp://)
- Add `partition-all` for lazy partitioning with support for infinite sequences
- Add `line-seq` for lazy line-by-line file reading with automatic resource cleanup
- Add `file-seq` for lazy recursive directory traversal
- Add `read-file-lazy` for lazy chunked file reading
- Add `csv-seq` for lazy CSV parsing
- Make `concat`, `mapcat`, `interpose`, `map-indexed`, `interleave`, variadic `map`, and `partition` fully lazy with infinite sequence support
- Add `lazy-seq` and `lazy-cat` macros for user-defined lazy sequences
- Add `conj` function for Clojure-compatible collection building
- Add string index support to `contains?` for Clojure compatibility

### Changed
- Rename collection parameters from `xs` to `coll` throughout core functions
- Make `assoc`/`dissoc` primary functions with `put`/`unset` as deprecated aliases
- Deprecate `push` in favor of `conj`
- Improve docblocks with examples for core library functions across all namespaces
- Optimize compilation pipeline with multi-level caching (~25-35% faster compilation, ~10% less memory usage)

### Fixed
- Fix `into` to work correctly with `PersistentList` and other `ConcatInterface` types that don't implement `PushInterface`
- Fix `contains?` to use character count instead of byte count for multibyte UTF-8 strings

## [0.25.0](https://github.com/phel-lang/phel-lang/compare/v0.24.0...v0.25.0) - 2025-11-09

### Added
- Memory-efficient lazy sequences with chunked evaluation
  - Add `doall` and `dorun` for controlling lazy sequence realization
  - Make `map`, `filter`, `take`, `drop`, `drop-while`, `take-while`, `take-nth`, `keep`, `keep-indexed`, `distinct`, `dedupe`, and `partition-by` fully lazy
  - All lazy functions support infinite sequences and preserve metadata
- Simplified release process with `OFFICIAL_RELEASE` environment variable
  - Build official release PHAR: `OFFICIAL_RELEASE=true build/phar.sh`
  - Automatically embed release flag via `.phel-release.php` config
  - Works seamlessly with both PHAR and Composer dependencies
- Optimized PHAR build with smart caching and compression
  - Vendor caching based on `composer.lock` hash
  - GZ compression to reduce file size
  - Progress indicators for build visibility

### Fixed
- Memory exhaustion in `partition-by` and `dedupe` with infinite sequences
- Export function name to PHP
- Support for php-timer and console `^6.0|^7.0|^8.0`

### Changed
- Optimize `PhelCallerTrait` and `RequireEvaluator` performance

## [0.24.0](https://github.com/phel-lang/phel-lang/compare/v0.23.1...v0.24.0) - 2025-10-26

- Allow null on Phel static collections (vector, list, map, set) arguments
- Fix `argv` from GLOBALS when does not exist
- Add `load` and `in-ns` special forms
- Upgrade `gacela:1.11` and `psalm:7.0-beta` and improve type safety

## [0.23.0](https://github.com/phel-lang/phel-lang/compare/v0.22.2...v0.23.0) - 2025-10-05

- Fix `doc` command on phar command
- Add `catch` and `finally` functions to the API docs
- Add `--format` json option to `phel doc` command
- Allow `ns` form with multiple modules within a single `:require` form
- Add `--debug` flag to suppress test output and write debug info to ./phel-debug.log
  - Optional Phel file filter using equals syntax: `--debug="core"` or `--debug="boot"`
- Add `phel eval` command
- Make `repl` the default command when no args are provided to the executable
- Move Facade Interfaces to Shared Module
- Remove deprecated `*compile-mode*` in favor of `*build-mode*`
- Allow multiple entries in `:use` form
- Add `doseq` macro for side-effectful comprehensions

## [0.22.2](https://github.com/phel-lang/phel-lang/compare/v0.22.1...v0.22.2) - 2025-09-23

- Fix `recur` function docs on API

## [0.22.1](https://github.com/phel-lang/phel-lang/compare/v0.22.0...v0.22.1) - 2025-09-23

- Fix `get` on php callable

## [0.22.0](https://github.com/phel-lang/phel-lang/compare/v0.21.0...v0.22.0) - 2025-09-22

- Fix REPL require from `repl.phel` fails
- Fix `meta` on definitions
- Fix `argv` to return a vector instead of a native PHP array
- Fix core functions/macros should persist metadata
- Fix `NsSymbol` namespace validation
- Add new array functions with nested lookup
    - php/aget-in
    - php/aset-in
    - php/aunset-in
    - php/apush-in
- Generate relative file paths and GitHub URLs for Phel functions
- Improve public Api module
    - Add new methods and remove deprecated ones from PhelFunction
- Add `*file*` to return the current source file's path
- Add comma separator between key value pairs in the hash-map printer
- Add set shortcut syntax eg `#{1 2 3}`
    - Use new set shortcut syntax for printer
- Add `into` function
- Discarding commas in printer output with new `CommaNode`
- Use custom if-throw for `:pre/:post` metadata conditions
    - Allow disabling via config to improve performance
- Return empty string for `__FILE__` and `__DIR__` in the REPL
- Improve PhelFunction
    - Deprecate methods in favor of its public properties
    - Remove `rawDoc`
    - Add `meta` with all possible metadata
- Increase PHPStan level to 5
- Add `phel\debug` namespace with additional debugging helpers such as `spy`, `tap`, and enhanced tracing utilities
- Remove the legacy `phel\trace` namespace in favor of `phel\debug`

## [0.21.0](https://github.com/phel-lang/phel-lang/compare/v0.20.0...v0.21.0) - 2025-09-01

- Fix autoload phel classes from phar
- Fix vector and hash map literals in try
- Fix REPL symbol resolution issue loading current dir
- Fix destructured names not available in :pre assert
- Fix func metadata parsing conflict with literal hashmap
- Fix `phar repl` not resolving ns from the working dir
- Add `#_` macro for inline comments
- Add `;` as alternative comment character
- Add multiline comments using `#|` and `|#`
- Add `:pre/:post` function metadata conditions
- Add map destructuring with `:keys` and `:as`
- Add macro auto-gensym syntax
- Add multi-arity function support
- Allow passing CLI flag arguments to `phel run` scripts
- Show the current Phel version in the REPL welcome message
- Append commit hash to the version string when not on a tagged release
- Update default error log file

## [0.20.0](https://github.com/phel-lang/phel-lang/compare/v0.19.1...v0.20.0) - 2025-08-25

- Fix `map` function exhaustion with empty collections
- Fix `contains-value?` with `nil` value
- Fix set `difference` errors with certain input
- Fix `require` loading code without `*build-mode*`
- Allow `reduce` without an initial value and remove `reduce2`
- Add new functions:
    - `select-keys`
    - `median`
    - `slurp`
    - `spit`
    - `assoc` alias for `put`
    - `assoc-in` alias for `put-in`
    - `dissoc` alias for `unset`
    - `dissoc-in` alias for `unset-in`
    - `drop-last`
    - `pad-both`
    - `zipmap`
    - `repeat`
    - `repeatedly`
    - `iterate`
    - `every?` alias for `all?`
    - `not-every?`
    - `not-any?`
    - `some`
    - `not-empty`
    - `constantly`
    - `last`
    - `butlast`
    - `partition-by`
    - `dedupe`
- Add new macros:
    - `some->`
    - `some->>`
- Extend `take` to handle PHP Traversable inputs
    - enabling safe slicing of generators and other iterables for infinite list scenarios
- Enhance `php/->` for nested calls
- Auto-assign-author GH workflow
- Avoid coercing `nil` to 0 in math operations
- Add `Phel` as public entry class instead of `\Phel\Phel` 
- Use `Phel` as proxy for singleton `Registry` methods
    - Use new `list`, `vector`, `set` and `map` constructors
- Move `variable`, `symbol`, `keyword` from `TypeFactory` to `Phel`

## [0.19.1](https://github.com/phel-lang/phel-lang/compare/v0.19.0...v0.19.1) - 2025-08-03

- Fix `require` broke in repl
- Fix trigger warning in tests
- Add `symbol` function
- Suppress possible notice when PHP falls back to the system temp directory

## [0.19.0](https://github.com/phel-lang/phel-lang/compare/v0.18.1...v0.19.0) - 2025-08-02

- Fix `DefException` Emitter compatible with PHP 8.4
- Fix repl prints unicode chars
- Fix repl with multi-line string literals with brackets
- Improve performance
  - `TopologicalNamespaceSorter`
  - `DependenciesForNamespace`
  - `NamespaceExtractor`
- Add core functions
  - `unset-in`
  - `memoize`
- Add base64 functions
  - `base64/encode`
  - `base64/decode`
  - `base64/encode-url`
  - `base64/decode-url`
- Replaced array-based queue handling with a more efficient `SplQueue` in the dependency traversal logic
- Optimize test command - disabling the sourcemap

## [0.18.1](https://github.com/phel-lang/phel-lang/compare/v0.18.0...v0.18.1) - 2025-06-18

- Add `phel doctor` command
- Fix load core namespaces for PHAR
- Fix repl on PHAR
- Change the default error log file to `/tmp/phel-error.log`
- Add new config parameter `tempDir`

## [0.18.0](https://github.com/phel-lang/phel-lang/compare/v0.17.0...v0.18.0) - 2025-06-09

- Add functions
  - `str/pad-left`
  - `str/pad-right`
  - `trace/dotrace`
  - `trace/dbg`
- Add `phel ns [inspect]` command
- Create a script to build a single executable PHAR
- Enable opcache on file compilation
- Add `--clear-opcache` option to `phel run`

## [0.17.0](https://github.com/phel-lang/phel-lang/compare/v0.16.1...v0.17.0) - 2025-06-01

- Fix `php/echo` does not work (#729)
- Fix `hash-map` malfunction notice (#786)
- Fix header injection vulnerability (#803)
- Fix multibyte functions in capitalize, lower-case, and upper-case (#805)
- Fix null checks on Analyzer forms (#807)
- Fix calling `php/$_SERVER` more than once errors on repl (#789)
- Fix inmutable global binding (#811)
- Fix private symbol refer resolution (#813)
- Fix HTML macro when using when forms (#817)
- Fix REPL problems with multi-line string literals containing brackets (#818)
- Fix REPL ns evaluation (#820)
- Fix Tab characters trigger shell autocompletion on REPL
- Fix cannot reload user namespace (#832)
- Enable REPL autocompletion using functions in the Registry (#821)
- Add `time` macro
- Add `doto` macro (#791)
- Add `if-let` and `when-let` macros (#795)
- Add native yield support (#802)
- Add `phel/str/repeat`
- Add strings wrapped with quotes on REPL (#806)
- Add `macroexpand` function (#810)
- Add source filename:line on repl exceptions (#812)
- Add `defexception` special form (#819)
- Add completion to REPL (#821)
- Add `phel->php` and `php->phel` functions (#829)
- Add `ApiFacade::replComplete` service (#830)
- Add `str\contains?` function (#833)
- Add display deprecated metadata on doc (#834)
- Improve vector performance (#823)
- Improve `DependenciesForNamespace` performance
- Use PHPStan level 2

## [0.16.1](https://github.com/phel-lang/phel-lang/compare/v0.16.0...v0.16.1) - 2024-12-13

- Add support for PHP 8.4

## [0.16.0](https://github.com/phel-lang/phel-lang/compare/v0.15.3...v0.16.0) - 2024-12-01

- Improved exception messages in the REPL
- Display the root source file in error messages to help debugging
- Enabled overriding the cache directory via the `GACELA_CACHE_DIR` environment variable (Gacela 1.9)
- Fixed issue where temporary files were not being removed in `Phel::run()`
- Removed unused `ExceptionHandler`

## [0.15.3](https://github.com/phel-lang/phel-lang/compare/v0.15.2...v0.15.3) - 2024-11-02

* Update dependencies & run rector (#758)
* Run in separate process the ApiFacadeTest (#759) 
* Install and run composer-normalize (#760)
* Add native phel symbols to ApiFacade (#764)

## [0.15.2](https://github.com/phel-lang/phel-lang/compare/v0.15.1...v0.15.2) - 2024-08-19

* Fix a result of `str/split-lines` is in the wrong order (#735)
* Fix `find` function for an empty vector (#737)
* Fix `some?` function for an empty vector (#741)
* Fix `binding` function for atom body (#748)
* Upgrade Gacela 1.8 (#752)

## [0.15.1](https://github.com/phel-lang/phel-lang/compare/v0.15.0...v0.15.1) - 2024-06-26

* Fix missing v0.15 version to `bin/phel` executable

## [0.15.0](https://github.com/phel-lang/phel-lang/compare/v0.14.1...v0.15.0) - 2024-06-22

* Fix add check for readline extension in REPL to handle missing dependencies (#712)
* Compatibility of lists and vectors in the cons function (#714)
* Fix deprecation notice for signed binary (#716)
* Fix deprecation notice for signed hexadecimals (#718)
* Fix deprecation notice for signed octals (#719)
* Check mandatory function parameters during compile time (#717)
* Improve output for doc command (#720)
* Introduce application layer (#721)
* Fix recursive private access (#727)

## [0.14.1](https://github.com/phel-lang/phel-lang/compare/v0.14.0...v0.14.1) - 2024-05-24

* Fix `bin/phel` after refactor

## [0.14.0](https://github.com/phel-lang/phel-lang/compare/v0.13.0...v0.14.0) - 2024-05-24

* Change `PhelConfig` default src and tests directories (#699)
* Fix `PhelBuildConfig` when using `trim` (#698)
* Fix `setMainPhpPath()` without directory or more than one (#697)
* Rename `PhelOutConfig` to `PhelBuildConfig` (#687)
* Fix `$` as named parameter in macros (#695)
* Add `phel/str` functions (#688)
  * `split`: Splits string on a regular expression
  * `join`: Returns a string of all elements in coll
  * `reverse`: Returns s with its characters reversed
  * `upper-case`: Converts string to upper-case
  * `replace`: Replaces all instances of match with replacement in string
  * `replace-first`: Replaces the first instance of match with replacement in string
  * `trim-newline`: Removes all trailing newline \n or return \r characters from string
  * `capitalize`: Converts first character of the string to upper-case, all other characters to lower-case
  * `lower-case`: Converts string to lower-case
  * `upper-case`: Converts string to upper-case
  * `trim`: Removes whitespace from both ends of string
  * `triml`: Removes whitespace from the left side of string
  * `trimr`: Removes whitespace from the right side of string
  * `blank?`: True if s is nil, empty, or contains only whitespace
  * `starts-with?`: True if string starts with substr
  * `ends-with?`: True if string ends with substr
  * `includes?`: True if string includes substr
  * `re-quote-replacement`: Escaping of special characters
  * `escape`: Return a new string, using cmap to escape each character from string
  * `index-of`: Return index of value in string, optionally searching forward
  * `last-index-of`: Return last index of value in string, optionally searching backward
  * `split-lines`: Splits string with on \n or \r\n

## [0.13.0](https://github.com/phel-lang/phel-lang/compare/v0.12.0...v0.13.0) - 2024-04-17

* Require PHP>=8.2
* Add `PhelOutConfig->setMainPhpPath()`
  * in favor of `->setMainPhpFilename()`
* Add `phel fmt` alias for format (#673)
* Add support for numeric on `empty?` (#683)
* Add `PhelConfig->setNoCacheWhenBuilding()` (#685)
* Fix `interleave` allowing nil keys and values (#682)
* Fix `**build-mode**` flag when building the project (#686)

## [0.12.0](https://github.com/phel-lang/phel-lang/compare/v0.11.0...v0.12.0) - 2023-11-01

* Do not create the entrypoint when namespace isn't set
* Fix `AtomParser` decimal regex
* Improve output for all PHP errors
* Move `phel` to `bin/phel`
* Add `phel --version` option
* Notify user when running a non-existing file or namespace

## [0.11.0](https://github.com/phel-lang/phel-lang/compare/v0.10.1...v0.11.0) - 2023-08-26

* Create a PHP entry point when using `phel build`
  * Extract building "out" settings into `PhelOutConfig`
* Improve the error display for PHP Notice messages
* Save all errors in a temp `error.log` file
  * You can change the error.log file path with `PhelConfig::setErrorLogFile(str)`

## [0.10.1](https://github.com/phel-lang/phel-lang/compare/v0.10.0...v0.10.1) - 2023-05-12

* Fixed the `phel\repl\doc` function.
* Use all ns by default on Api's `PhelFnNormalizer`.

## [0.10.0](https://github.com/phel-lang/phel-lang/compare/v0.9.0...v0.10.0) - 2023-04-01

* Added default format paths: 'src', 'tests' (#569)
* Deprecate `*compile-mode*` in favor of `*build-mode*` (#570)
* Added `--testdox` argument to `phel test` command (#567)
* Added support for fluid configuration in `phel-config.php` (#494)
* Enable gacela cache filesystem by default (#576)
* Fix `php/apush`, `php/aset` and `php/aunset` for global php arrays (#579)

## [0.9.0](https://github.com/phel-lang/phel-lang/compare/v0.8.0...v0.9.0) - 2023-02-05

* New Api module which exposes (via the `ApiFacade`) the functions documentation of Phel (#551)
* New `phel doc` command (#552)
* Rename command `phel compile` to `phel build` (#555)
* Added config parameter `ignore-when-building` (#557)
* Added config parameter `keep-generated-temp-files` (#553)
* Allow underscores in decimal numbers (#564)

## [0.8.0](https://github.com/phel-lang/phel-lang/compare/v0.7.0...v0.8.0) - 2023-01-16

* Allow strings on `empty?` (#492)
* Improved error message when using strings on `count` (#492)
* Added `contains-value?` function (#520)
* Added `phel/json` library (#489)

## [0.7.0](https://github.com/phel-lang/phel-lang/compare/v0.6.0...v0.7.0) - 2022-05-05

* Added: `merge-with` function
* Added: `deep-merge` function
* Added: `NamedInterface` interface for Symbol and Keyword
* Added: `name` function
* Added: `namespace` function
* Added: `full-name` function
* Added: `http/uri-from-string` function
* Added: Uri structs implements `Stringable` interface
* Added: `http/response-from-map` function
* Deprecated: `http/create-response-from-map` in favor of `http/response-from-map`
* Added: `http/response-from-string` function
* Deprecated: `http/create-response-from-string` in favor of `http/response-from-string`
* Added: `attributes` field to `request` struct. Allows developers to enrich the request with custom data
* Added: `http/request-from-map` function
* Bugfix: #443
* Added: Support for PHP Array literals (#451)
* Added: `read-string` function
* Added: `eval` function
* Added: `compile` function
* Bugfix: #467 Failed to run a REPL on Windows
* Bugfix: #471 Reusing a local variable in a function fails.

## [0.6.0](https://github.com/phel-lang/phel-lang/compare/v0.5.0...v0.6.0) - 2022-02-02

* Drop support for PHP 7.4. (#403)
* Support for PHP 8.0 and 8.1. (#406)
* Printer: render `<function>` when printing a function and not `<PHP-AnonymousClass>`. (#404)
* Add a `:reduce` option for the for-loop. (#405)
* Removed deprecated table, array and set mutable data structures. (#407)
* Add feature to require php files in ns statement. (#421)
* Remove all calls to GlobalEnvironmentSingleton in the compiled code. (#408)
* Add a new phel core function: `coerce-in` (#424)
* Introduce a registry class to store the definitions instead of `$GLOABLS`. (#423)
* Evaluate meta data in the special form `def` (#426)
* Add support for inline optimization. (#427)
* Fixed bug in compile command (#428, #410)
* Improved documentation (#432)

## [0.5.0](https://github.com/phel-lang/phel-lang/compare/v0.4.0...v0.5.0) - 2021-12-17

* Added variables.
* Added namespaced keywords.
* Added interfaces and their usage in structs.
* Added a compile command

## [0.4.0](https://github.com/phel-lang/phel-lang/compare/v0.3.3...v0.4.0) - 2021-10-05

* Removed `load` function in `phel\core`
* Pass by value the array (1st argument) to `push` (#306)
* **Breaking**: Configuration will be loaded from `phel-config.php` and not from `composer.json`
  * The `loader` config parameter has been removed. Please use `src-dirs` now.
  * The `loader-dev` config parameter has been removed. Please use `test-dirs` now.
  * The `tests` config parameter has been removed. Please use `test-dirs` now.
  * A `vendor-dir` config parameter has been introduced. Default value is `vendor`.
* **Breaking**: Dependencies in vendor will only be recognized if the vendor project has a `phel-config.php` file. All old project that have the configuration inside the `composer.json` will not be detected anymore.
* The `phel-composer-plugin` is obsolete and is not need it anymore.
* The way code in Phel is compiled has changed:
  * Before it was bottom up: If a phel file was evaluated it continued only after all dependencies have been evaluated.
  * Now it is top down: The compiler first creates a dependencies graph and start to evaluate files with no dependencies before others.
* The `PhelRuntime` was removed and is not needed anymore.
* Internal refactoring:
  * All commands have been moved to their associated modules.

## [0.3.3](https://github.com/phel-lang/phel-lang/compare/v0.3.2...v0.3.3) - 2021-06-04

* Removed `load` function.
* Fixed `RangeIterator` for Vectors (#302)

## [0.3.2](https://github.com/phel-lang/phel-lang/compare/v0.3.1...v0.3.2) - 2021-05-25

* Transient Maps can grow bigger than 16 elements (#289)
* Added a filter option to the test command. (#285)
* Added execution time and resource usage to the test command (#284)
* Disallows unexpected keywords in ns (#286)

## [0.3.1](https://github.com/phel-lang/phel-lang/compare/v0.3.0...v0.3.1) - 2021-05-16

* For loop will now return a vector instead of an array (#276)

## [0.3.0](https://github.com/phel-lang/phel-lang/compare/v0.2.0...v0.3.0) - 2021-05-12

* New persistent data structures (#244)
  - The old data structures have been deprecated and will be removed in the next version.
* Rename `fmt` command to `format` (#248)
* Added new function `take-last` (#245)
* Added new function `re-seq` (#245)
* `partition` now returns all items if the length of the given array is lower than the given size n. (#246)
* `partition` now returns remaining items if the size of the remaining array is lower than given size n. (#246)
* Added new function `contains?` (#267)

## [0.2.0](https://github.com/phel-lang/phel-lang/compare/v0.1.0...v0.2.0) - 2021-02-22

* Call Phel functions from PHP (#209)
* Set PHP object properties from Phel (#235)

## [0.1.0](https://github.com/phel-lang/phel-lang/compare/da837505e3a67ad6023f7cbc3ac57cf8f7473e66...v0.1.0) - 2021-01-31

Initial release

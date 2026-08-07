# Compiler Module

Core compilation pipeline: Phel source → tokens → AST → analyzed nodes → PHP code.

## Public API (Facade)

| Category | Methods |
|----------|---------|
| Compilation | `compile`, `compileForCache`, `compileForm` (return `EmitterResult`) |
| Evaluation | `eval(string, CompileOptions)`, `evalForm(mixed, CompileOptions)` |
| Pipeline | `lexString → TokenStream`, `parseNext → ?NodeInterface`, `parseAll → FileNode`, `read → ReaderResult`, `analyze(mixed, NodeEnvironmentInterface) → AbstractNode` |
| Tooling | `readFormsBestEffort(code, source) → Generator` of top-level forms; never throws (parse failure ends the stream, read failure skips the form). For linting/indexing/completion over a buffer mid-edit; `Application/BestEffortFormReader` |
| Macros | `macroexpand1`, `macroexpand` |
| Environment | `initializeGlobalEnvironment`, `resetGlobalEnvironment`, `isGlobalEnvironmentInitialized`, `getGlobalEnvironment`, `initializeNewGlobalEnvironment`, `setGlobalEnvironment` |
| Namespace state | `getNamespaceEnvironmentData`, `restoreNamespaceEnvironmentData` |
| Debugging | `enableDebugLineTap`, `disableDebugLineTap` |
| Utility | `encodeNs` (PHP-form via `Munge::encodePhpNs`), `hasBalancedParentheses` |

## Dependencies

- **Filesystem** — file I/O (`FilesystemFacadeInterface::class`, the module's only Provider entry).
- **Config** — `PhelConfig` data model, wrapped by `CompilerConfig` (`assertsEnabled()`, `warnDeprecationsEnabled()`, `isIntermediateCacheEnabled()`, `getCacheDir()`).
- **Lang** — the compiler's widest edge by far (~200 files): every phase after the lexer reads and produces `Phel\Lang` values, and the emitter writes their FQNs into generated PHP.
- **Shared** — `Munge`, `Printer`, exceptions, `SourceMap\VLQ`. Shared points back through `Facade/CompilerFacadeInterface`; see the "Compiler Back-Edge" section of `Shared/CLAUDE.md`.

The source map is split across the boundary on purpose: the writer (`Domain/Emitter/OutputEmitter/SourceMap/SourceMapGenerator`, `SourceMapState`) is emitter state and stays here, while the reader (`Shared\SourceMap\SourceMapConsumer`) lives in Shared because Command decodes maps too and must not `new` a compiler-internal class.

## Phase Pipeline

Lexer (source → `TokenStream`) → Parser (→ `FileNode` parse tree) → Reader (→ `ReaderResult` Phel data) → Analyzer (→ `AbstractNode` AST with `NodeEnvironment`) → Simplifier (→ optimized AST) → Emitter (→ `EmitterResult` PHP code).

- Lexer `Token` and parse-tree nodes live in `Phel\Shared\Parser\Node`; `ExpressionParserFactory` produces them (sub-parsers in `Domain/Parser/ExpressionParser/`).

### Interop shorthand expansion

Clojure-style interop spellings are sugar, expanded to `php/*` forms before analysis, never registered as special forms (`LanguageSurfaceSpecTest` fails on a spec table row with no dispatch entry):

- Call position — `AnalyzePersistentList`: `(.m obj …)`, `(.-field obj)`, `(\C/m …)`, `(\C. …)`.
- Value position — `Domain/Analyzer/TypeAnalyzer/QualifiedMemberExpander`, reached from `AnalyzeSymbol` when global resolution finds nothing: `\C/CONST` → `php/::`, `\C/$prop` → `php/::` (static property, no reflection: the sigil decides), `\C/m` → `php/callable`, `\C/.m` → an `fn` of the receiver.

`NodeEmitter/PhpObjectSetEmitter` shares `PhpObjectCallDispatchResolver` with the read path, so a class-name target is static however it was written, and it prefixes a bare static member name with `$`: a class constant is not assignable, so an assignment can only mean the property (#2907).

`QualifiedMemberExpander` reflects the resolved class to tell a static method from a constant. A class carrying both under one name resolves to the **constant** (pre-existing behaviour, and the reason `\C/new` is not a constructor); an unresolvable or unloadable class falls back to the constant reading so the error stays what it was.

`Domain/Analyzer/QualifiedMemberSyntax` is the single statement of *how a class member is spelled* (namespace reads as a class reference, name starts like a PHP member). Call position, value position and `phel.core/set!` all decide with it, so `(\Foo/m 1)`, `\Foo/CONST` and `(set! \Foo/slot v)` cannot disagree; `set!` re-spells it in Phel because the stdlib cannot import `@internal` compiler classes. Purely lexical: `PhpClassLike` answers existence.

Bare host symbols have one separate collision rule, in `SymbolResolver::resolveBareHostSymbol()`: an existing PHP class/interface/trait/enum wins over a same-named global constant, including all-caps classes such as `PDO`; `php/NAME` is the explicit constant escape hatch. `Domain/Analyzer/PhpClassLike` is the single class-like existence predicate (also used by `UseAliasRegistrar`). Keep it autoloading, otherwise the same source changes meaning according to which earlier form happened to load the class.

### Simplification pass

Runs after `ConstantFolder` (in `Domain/Analyzer/TypeAnalyzer/Simplification/`):

- Drops pure non-tail expressions from `(do ...)` via `PureExpressionDetector`.
- Inlines calls at opt level >= 2 via `CallInliner` (purity from `ConstantFolder` for known calls, `SymbolicPurityDetector` for structural checks).
- `^:pure` metadata opts a `defn` into inlining trust (author owns correctness).

### Reader-result cache

Opt-in, off by default (`CompilerConfig::isIntermediateCacheEnabled()`). Wired only into `createCodeCompilerForCache` (build path), never the REPL path.

- `CodeCompiler` persists each source's read results via `Domain/Cache/ReaderResultCacheInterface`.
- Enabled → `Infrastructure/Cache/FileSystemReaderResultCache` (gzip'd `serialize` under `<cacheDir>/read-result/`, key = `md5(version|optLevel|source)`); else `NullReaderResultCache`.
- Warm hit skips lex/parse/read and replays each form's recorded read-phase gensym delta (`Domain/Cache/CachedReaderResult` = `ReaderResult` + delta) before analysis, so the shared `Symbol::gen()` counter follows the cold-compile trajectory.
- GOTCHA: replayed forms are deserialized Phel values, so anything used as a map key must compare by value, not identity (`Keyword::equals`/`Symbol::equals`) — else a cached keyword-keyed lookup silently misses on replay.
- Emitted PHP is stable for a given counter trajectory, but gensym names are process-global; a build mixing fresh compiles with compiled-code-cache hits can renumber them (pre-existing, independent of this cache).

## Key Constraints

- Never bypass a phase; each consumes only output of the previous.
- Analyzer nodes must carry `NodeEnvironment` with correct context.
- Emitter must handle every node type; missing cases throw, not silently skip.
- Special forms registered centrally; no ad-hoc handling in analyzer loop.
- Source locations must propagate through all phases for error reporting.
- Do NOT rename `GlobalEnvironmentSingleton` — its FQN is baked into cached `.phel` files.
- Do NOT rename `LoadEmitter`'s `Phel\Lang\LoadClasspath::class` (the `(load ...)` classpath store) — `LoadEmitter` bakes its FQN into generated PHP. It lives in `Lang` because its state is the `*load-classpath*` slot in `Lang\Registry`.

## Type-Specialized Emission

Analyzer tracks param/return types via `ParamTypeInferrer` and `ReturnTypeInferrer`, grafting `:tag` meta onto binding symbols. Contract: propagate only analyzer-published types, never fabricate.

Two halves, by family:

- **Eligibility** — `*Specialization` classes in `Domain/Emitter/OutputEmitter/`: `NumericOperationSpecialization`, `TypePredicateSpecialization`, `TypedValueSpecialization`, `TypedCollectionMethodSpecialization`, `AssocConjSpecialization`, `GetInSpecialization`, `AtomMethodSpecialization`, `NilAndBooleanCheckSpecialization`, `ReduceSpecialization`. `CallSpecialization` aggregates them.
- **Emission** — one `Specialized/*CallEmitter implements SpecializedCallEmitterInterface` per family under `NodeEmitter/Specialized/`. `CallEmitter` builds them once and dispatches by looping `tryEmit()` before the generic call path.

Family predicates are disjoint, so chain order between families is not significant.

To add a family: write a `*Specialization` eligibility class, register it in `CallSpecialization::isSpecialized()`, and add the matching `Specialized/*CallEmitter` to `CallEmitter`'s ordered list.

GOTCHA: only eager core fns can be lowered to a native loop. `reduce` (3-arity) qualifies. `map`/`filter` do NOT — they return a `LazySeq` over a `Seq::map`/`Seq::filter` generator and `copy-meta` the source; an eager `foreach` lowering would change the return type, break infinite/expensive seqs, and shift side-effect timing. They also gain little: `f` is handed to the generator once, so there is no per-element registry dispatch to remove.

## Generated-Class Attributes & Typed Signatures

`DefStructEmitter`, `DefInterfaceEmitter`, and `DefEnumEmitter` read per-symbol metadata to enrich generated PHP. They share `PhpAttributeEmitterTrait` (tag + `:php/attr` reading) and the pure `Phel\Shared\PhpAttributeRenderer`. All opt-in; untagged forms are byte-identical to before.

`:tag` value forms: bare symbol/string = verbatim (`?int`/`self`/`\DateTime` pass through); list = union (`a|b`); vector = intersection (`a&b`).

### defstruct (`DefStructEmitter`)

- Field `^{:tag <type>}` → typed property (`protected int $id;`).
- `^{:php/attr [...]}` on struct name (class-level) or field (property-level) → PHP 8 attributes.
- `^{:php/json true}` on struct name → implements `\JsonSerializable` (`jsonSerialize()` returns the field map).
- `^{:php/stringable true}` on struct name → declares `\Stringable`.
- `^:php/readonly` on struct name → `readonly` typed properties (untagged fields default `readonly mixed`) + a constructor-rebuilding `put()` override so persistent updates work. Stays a plain `final class` (cannot be a PHP `readonly class` because `AbstractPersistentStruct` is not readonly).
- `:php` marker opens a block of bare PHP magic methods (`__invoke`/`__toString`/`__get`) emitted on the class with no interface (`PhpBlockAnalyzer`, carried as a `DefStructInterface` with empty name that `DefStructEmitter` drops from `implements`). Custom `__invoke` must be 1-arg or variadic (`PhpBlockAnalyzer` rejects bad arity).

### definterface (`DefInterfaceEmitter`)

- Method arg `^{:tag <type>}` → typed param; method name `:tag` → return type.
- `^{:php/attr [...]}` on interface name, method, or method **parameter** → interface-/method-/parameter-level attributes (parameter form inlined: `show(#[\Autowire] string $repo)`).
- Trailing `:php/const` block → typed class constants: `:php/const (^{:tag int} MAX 100)` → `const int MAX = 100;` (value must be int/float/string/bool/nil literal; `DefInterfaceSymbol`/`PhpClassConst`/`DefInterfaceEmitter`).
- The `definterface` macro only wraps method forms in Phel fns; the const block passes through to `definterface*` only.

### defenum (`defenum*`, `DefEnumSymbol`/`DefEnumNode`/`DefEnumEmitter`)

- Emits a native PHP `enum`; cases are keyword-named with an optional `int`/`string` value (all-or-none → backed vs pure enum). Guarded by `enum_exists`.
- `^{:php/attr [...]}` on enum name → class-level attributes.
- Optional implementations tail after cases (interface symbols + methods, plus a `:php` block) parsed via shared `InterfaceImplementationsAnalyzer`, emitted as `implements` + methods via `MethodEmitter`.

### Shared across constructs

- `InterfaceImplementationsAnalyzer` (used by `defstruct` and `defenum`) parses interface symbols (reflection-validated) and `:php` blocks (become a `DefStructInterface` with empty name).
- `PhpBlockAnalyzer::analyze` takes an `enforceInvokeArity` flag (true only for structs, whose map `__invoke` constrains arity).
- `^{:php/doc <str|[str...]>}` on any name/field/method → PHPDoc block (one-line string or multi-line list/vector) above the construct, so phpstan/psalm see generated classes as typed.
- `^:php/override` on a method (defstruct/defenum interface impls, definterface methods) → `#[\Override]` (PHP 8.3); `PhpAttributeEmitterTrait::phpAttributeLines` renders it ahead of explicit `:php/attr` lines. Struct/enum inline method impls emit method-level `:php/attr`/`:php/doc`/`^:php/override` too.
- Export wrappers carry the same `:php/attr` via `Interop`'s `CompiledPhpMethodBuilder` (see `src/php/Interop/CLAUDE.md`).

## Compiler Diagnostics

Two channels, differing only in the gate. Both raise through `Domain/Diagnostic/ErrorNotice::raise($message, $level)`, the compiler's single `trigger_error()` call, which pins `display_errors` to stderr for the duration (see the deprecation section below for why).

| Channel | Level | Gate |
|---|---|---|
| `Domain/Deprecation/DeprecationWarnings` | `E_USER_DEPRECATED` | off unless `--warn-deprecations` |
| `Domain/Diagnostic/CompilerWarnings` | `E_USER_WARNING` | always on |

`CompilerWarnings` is for a diagnostic that has already changed what the program does, so staying quiet is itself the bug. It owns the bundled-stdlib suppression (reusing `DeprecationWarnings::isBundledStdlibSource()`) and the per-`(file, subject)` dedup; `reset()` exists for test `tearDown()`. Its one detector today:

| Detector | Catches |
|---|---|
| `Domain/Analyzer/Environment/ReferShadowWarner` | a `def` whose name is already `:refer`red into the same namespace (#2897). Called from `DefSymbol` before `addDefinition`, so it warns once at `def` time like Clojure, not at every call site. Anchors on the *name* symbol: the enclosing list carries a `defn` expansion origin in `src/phel/core/defs.phel`, which the stdlib suppression would silence |
| `Domain/Analyzer/Environment/ClassShadowWarner` | a `def` whose name is a loadable PHP class (#2876). Same hook point and the same reason to warn at `def` time. Filters on a PHP-identifier regex before probing, because `PhpClassLike::exists()` autoloads and `def` runs thousands of times loading the stdlib. Warns rather than refusing, which is what Clojure does; refusing is breaking and belongs to the major that drops the leading `\` |

Not to be confused with `Phel\Lsp\Application\Diagnostics` (LSP publishing) or `Phel\Shared\Api\Diagnostic` (the Lint value object). Same word, unrelated concepts.

## Deprecation Warnings

`Domain/Deprecation/DeprecationWarnings` is the single process-wide switch for every `E_USER_DEPRECATED` notice the compiler raises, syntax and definition alike. Off by default; turned on by `--warn-deprecations` (`Console\Application\WarnDeprecationsFlag`), `PHEL_WARN_DEPRECATIONS`, or the `warn-deprecations` config key (`CompilerFactory::createAnalyzer()`). It owns five things so no detector re-implements them: the enabled flag, the bundled-stdlib suppression, the `(file, subject)` dedup, the macro-expansion attribution, and the syntax message shape.

Detectors detect and nothing else — they hold no flag, no dedup table, and no emitter, so there is exactly one gate and one place to enable:

| Detector | Catches |
|---|---|
| `Application/Lexer` | bare `#` comments, `#\| ... \|#` blocks, `\|()` short fns, `,`/`,@` unquote |
| `Domain/Reader/QuasiquoteTransformer` | `$` auto-gensym |
| `Domain/Analyzer/Environment/BackslashSeparatorDeprecator` | `\` namespace separator |
| `Domain/Analyzer/Environment/DeprecatedDefinitionWarner` | any resolved definition whose meta carries `:deprecated` (`:superseded-by` names the replacement); works for project code too |
| `Domain/Deprecation/SupersededFormDeprecator` | a list head that is `php/new`, `php/->`, `php/::` or `set-var` — special forms Phel already says the Clojure way (#2877, #2888). Runs in `AnalyzePersistentList::analyze()` **before** the shorthand expansions, which rewrite `(.m obj)` into `(php/-> obj (m))`; after them every shorthand would warn. It also ignores an unlocated head, which is how `QualifiedMemberExpander`'s synthesized `php/::` form stays quiet |

- Never suppress a notice with `@`: that hides it unconditionally, so a `--warn-deprecations` run prints nothing. Call `warn()` / `warnForSource()` / `warnOnceForSource()` / `warnSyntax()` instead.
- They route through `Domain/Diagnostic/ErrorNotice::raise()`, which pins `display_errors` to `stderr` for the duration of the `trigger_error()` call (skipped when display is already off or already on stderr, so the redirect never *enables* a silenced notice). The case that forced this was `MethodEmitter`'s `^:reference` check, which ran during emission inside the emitter's `ob_start()`: under PHP CLI's default `display_errors=1` the notice text landed in that buffer and was spliced into the generated PHP, failing the compile with `syntax error, unexpected token ":"` (#2827). That alias is removed and nothing detects during emission today, but keep new notices on `raise()`: a diagnostic must never be able to corrupt captured output.
- `syntaxMessage()` is the only way to phrase a syntax notice. It has nowhere to put a concrete removal version, which is the point: a named release ships and the message goes stale (#2783). `LexerTest::VERSION_REFERENCE` still guards it.
- `warnOnceForSource()` dedups per `(file, subject)` — used where one subject recurs across a file (a deprecated definition, a `\`-separated symbol). Syntax notices deliberately do not dedup: each occurrence is a separate edit.
- `isEnabledForSource()` / `isBundledStdlibSource()` drop notices whose source is phel's own `src/phel` or has no file, so only code the user can edit is flagged. The Lexer resolves it once per source, not per token. Paths are `realpath`-normalized (memoized, and only once warnings are on) so a stdlib file reached through a relative prefix still matches.
- `InvokeSymbol::enrichLocation()` stamps the call site onto forms a macro/inline expansion produced, and records the definition's own location as `SourceLocation::getExpansionOrigin()`. Detectors must never report against the call site: the `\` in `(delay ...)`'s expansion lives in `src/phel/core/lazy.phel`, not in the file that called it (#2827). Only location-less forms are stamped, so macro arguments keep the reader's positions and a `\` the user typed still reports against their file.
- `warnOnceAtOrigin()` is the one place that attribution rule lives; `warnSyntax()` applies it too. A detector hands over the location it found plus a `fn (string $file, int $line): string` message builder and gets: an unexpanded form reported where it sits, an expansion reported against the macro's file with ` (reached by expanding a macro at <call site>)` appended, a bundled-stdlib origin suppressed, and an unknown origin (`SourceLocation::unknown()`, no `:start-location` on the definition) silent rather than misattributed. Do not re-derive it per detector.
- `SymbolResolver::globalVarNode()` is the single `GlobalVarNode` construction point, so every resolved definition passes the `:deprecated` check exactly once. `GlobalEnvironment` injects both analyzer detectors into it.

## Global Environment

Process-wide singleton in `Domain/Analyzer/Environment/GlobalEnvironmentRegistry`.

- `GlobalEnvironmentManager` (Application) and `GlobalEnvironmentSingleton` (Infrastructure) both read/write the same slot.
- `GlobalEnvironmentSingleton` is retained as ABI shim; emitter writes literal `\Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton::getInstance()` calls into generated PHP (baked into cached `.phel` files — see rename constraint above).

## Namespace Encoding

Owned by `Phel\Shared\Munge` (see `src/php/Shared/CLAUDE.md`). Two encoders at different boundaries:

- `encodePhpNs` — backslash form, for PHP `namespace` declarations and class FQNs.
- `encodeRegistryKey` — dot form, for Phel registry lookups.

Analyzer uses dot-separated namespace internally; emission routes through `encodePhpNs` for PHP output.

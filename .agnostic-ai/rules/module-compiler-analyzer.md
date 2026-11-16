---
name: module-compiler-analyzer
description: 'Load when changing the compiler analyzer: interop shorthand, symbol resolution, destructuring, simplification, inlining or type tags.'
scope: src/php/Compiler/Domain/Analyzer
---

# Compiler Analyzer

Read `.agnostic-ai/rules/module-compiler.md` for the pipeline and constraints.

## Interop shorthand

Clojure-style interop spellings expand to `php/*` forms before analysis. Never register them as special forms: `LanguageSurfaceSpecTest` fails on a spec row with no dispatch entry.

- Call position (`AnalyzePersistentList`): `(.m obj …)`, `(.-field obj)`, `(\C/m …)`, `(\C. …)`.
- Value position (`TypeAnalyzer/QualifiedMemberExpander`, from `AnalyzeSymbol` when global resolution finds nothing): `\C/CONST` and `\C/$prop` → `php/::` (the sigil decides, no reflection), `\C/m` → `php/callable`, `\C/.m` → an `fn` of the receiver.
- `QualifiedMemberExpander` reflects the class to tell a static method from a constant. A name that is both resolves to the constant (so `\C/new` is not a constructor); an unloadable class falls back to the constant.
- `QualifiedMemberSyntax` is the one statement of how a class member is spelled. Call position, value position and `phel.core/set!` all use it; `set!` re-spells it in Phel because the stdlib cannot import `@internal` classes. It is purely lexical: `PhpClassLike` answers existence.
- `PhpIdentifier` validates raw host members, class segments and type tags before emission, including bytes above ASCII. Munge Phel declaration names before validation; host names are not munged. `ReservedDeclarationName` applies PHP type-name reservations separately, allowing keyword members and the type names `enum`, `resource` and `numeric`.

## Symbol resolution

Bare values and calls resolve in this order: lexical locals, own namespace definitions or interfaces, `:use` aliases, `:refer` bindings, core definitions. A qualified name keeps its explicit namespace.

Bare host symbols read by position, never by probing the class table (ADR 0016). `SymbolResolver::resolveBareHostSymbol()` reads an all-caps name as a global constant; `BareHostClass` re-reads it as a class wherever only a class can stand (member target in `PhpObjectCallSymbol`, `PhpNewSymbol`, `PhpCallableSymbol`), so `(WP_CLI/log "x")` emits `\WP_CLI::log("x")` whether or not the class loads. `BareHostClass` also recovers a class import that an own definition shadows. Lexical locals stay dynamic targets; `php/NAME` stays a constant. Never reintroduce probing: emitted PHP would depend on what the process had autoloaded, and the compiled-code cache freezes whichever ran first.

`PhpClassLike` is the one class-like existence predicate. It still answers for `:use` alias targets (asserted at compile time), lowercase-initial bare names (`stdClass`), and `Environment/AmbiguousBareHostWarner`, which warns when value position reads a constant that a loadable class shadows.

Bare `__FILE__` / `__DIR__` become `Ast/SourcePathNode`, not string literals. Keep them out of constant folding so a concatenation cannot erase the path before Build relocates artifacts. `LocalVarReferences` treats them as leaves.

`UnresolvedSymbolAdvisor` builds the unresolved-symbol error. A qualified symbol is searched only in its own namespace (alias resolved, plus `BundledSymbolIndex`); an unknown alias names the bundled namespace to require; a bare name from a bundled namespace names the `:require ... :refer`. `BundledSymbolIndex` regex-reads public defs from stdlib sources and never loads a namespace.

## PHP import aliases

`UseAliasRegistrar` rejects conflicting explicit targets with `PHEL004`. `NsSymbol` shares collision tracking across one `ns` form, so a later evaluation of the namespace can replace old imports. `UseAliasOrigin` separates replaceable default aliases from explicit imports. `NamespaceEnvironmentSerializer` keeps only the `default_alias` flag for imported targets.

## Local binding names

`TypeAnalyzer/SpecialForm/LocalBindingName` keeps parameters and local shadows in `let`/`loop`, `foreach` and `catch` apart from emitter scratch prefixes (`__truthy`, `__or`, `__phel`). Rebinding keeps parameter metadata.

`TrySymbol` gives a catch binding an inferred `object` type only after body analysis, and only when `LocalVarReferences::mayBeMutated()` proves it read-only. Argument or reference escapes, unknown callees or nodes, collection metadata and PHP caller-scope writers keep runtime dispatch. The walk includes nested fns; source symbol metadata stays untouched.

## Sequential destructuring

`TypeAnalyzer/SpecialForm/Binding/Deconstructor/VectorBindingDeconstructor` gives `[a b & r]` an indexed path: one `iv (php/instanceof v PersistentVectorInterface)` binding, then each position reads `(php/aget v i)` under `(php/=== iv true)`, else the exact `first`/`next` walk (trailing `next` included), so lazy seqs realize the same cells and scalars throw where they did.

- A vector's `& r` is `(.cdr v)` after one position, `Destructure::nthNext(v, i)` after more.
- Test `(php/=== iv true)`, not bare `iv`: the truthiness adapter costs the list walk more than the fast path saves.
- Added bindings except `v` carry `ReturnTypeInferrer::SYNTHETIC_BINDING`, so `(fn [[x y]] 1)` stays unannotated.
- `[]` and `[& r]` get no test.

## Simplification

`TypeAnalyzer/Simplification/` runs after `ConstantFolder`:

- `PureExpressionDetector` lets `(do ...)` drop pure non-tail expressions.
- `CallInliner` inlines calls at opt level >= 2 (purity from `ConstantFolder` for known calls, `SymbolicPurityDetector` for structure). `^:pure` opts a `defn` in; the author owns correctness.

`UpdateLiteralFnLowering`: `InvokeSymbol` lowers an analyzed `(update m k (fn [v x ...] body) x ...)` with a literal single-arity `FnNode` into the `let` the runtime `update` runs.

- Gates: opt level >= 2; never on a `:redef` or `:dynamic` `update`.
- Bindings land in the caller's PHP frame, so only return position outside a `try` (`NodeEnvironment::isWithinTry()`, reset by a fn body).
- The body is analyzed once in its own env (macros see the real `&env`, params untyped); declining builds the ordinary `CallNode`.
- Params become `let` bindings under fresh PHP names (`ParamRenamer`); a nested fn declines, since its captures name the param. `ExpressionContextRebuilder` moves the body's value to expression position.
- The `let` is marked `LetNode::withSplicedFnBodyAfter()`: `ParamTypeInferrer` stops there as at a fn body, `TailCallRewriter` carries the mark, `CallInliner` and `LetSimplifier` leave it alone.
- Body and extra arguments must pass the `SpliceableBody` allowlist. Extra arguments are restricted because the closure captured enclosing locals before they ran. Keep it an allowlist: a denylist kept leaking.
- `recur` to the fn, rest, multi-arity or named fns, tagged params and any return type keep the closure.

## Type tags

`ParamTypeInferrer` and `ReturnTypeInferrer` graft `:tag` meta onto binding symbols. Propagate only analyzer-published types; never fabricate.

`TagCanonicalizer` rewrites a tag naming a `:use` import to the rooted class where it is declared: `FnSymbol`, `AnalyzeBindingsTrait` (`let`/`loop`), `DefSymbol`, `ForeachSymbol`, `DefStructSymbol`, `DefInterfaceSymbol`. Union (list) and intersection (vector) tags are rewritten per member, since `PhpAttributeEmitterTrait` joins them verbatim. Downstream readers resolve through stateless `Shared\TagResolver`, never the active `:use` table. A new form binding a tagged symbol must canonicalize too, or imported tags emit unqualified.

Inferred tags replace AST symbols with metadata-bearing copies. `NodeEnvironment` clones share an object-keyed inferred-type table, so a late result (loop fixpoint, inferred defn param) reaches nodes analyzed earlier. Never restore in-place `Symbol::withMeta()` mutation.

Emitter-side specialization by tag: `.agnostic-ai/rules/module-compiler-emitter.md`.

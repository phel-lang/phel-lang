---
name: module-compiler-analyzer
description: 'Compiler analyzer: interop shorthand, simplification and type tags.'
scope: src/php/Compiler/Domain/Analyzer
---

# Compiler Analyzer

Part of the Compiler module; read `.agnostic-ai/rules/module-compiler.md` for the pipeline and constraints.

## Interop shorthand expansion

Clojure-style interop spellings expand to `php/*` forms before analysis, never registered as special forms (`LanguageSurfaceSpecTest` fails on a spec row with no dispatch entry):

- Call position (`AnalyzePersistentList`): `(.m obj …)`, `(.-field obj)`, `(\C/m …)`, `(\C. …)`.
- Value position: `Domain/Analyzer/TypeAnalyzer/QualifiedMemberExpander`, from `AnalyzeSymbol` when global resolution finds nothing: `\C/CONST` → `php/::`, `\C/$prop` → `php/::` (static property; the sigil decides, no reflection), `\C/m` → `php/callable`, `\C/.m` → an `fn` of the receiver.

`QualifiedMemberExpander` reflects the class to tell a static method from a constant. A class with both under one name resolves to the **constant** (why `\C/new` is not a constructor); an unresolvable or unloadable class falls back to the constant reading.

`Domain/Analyzer/QualifiedMemberSyntax` is the single statement of how a class member is spelled. Call position, value position and `phel.core/set!` all use it, so `(\Foo/m 1)`, `\Foo/CONST` and `(set! \Foo/slot v)` cannot disagree; `set!` re-spells it in Phel because the stdlib cannot import `@internal` classes. Purely lexical: `PhpClassLike` answers existence.

`Domain/Analyzer/PhpIdentifier` validates raw host members, class segments and type tags before emission, including PHP's identifier bytes above ASCII. Phel declaration names are munged before validation; host names are not. `ReservedDeclarationName` applies PHP type-name reservations separately, preserving keyword members and the allowed type names `enum`, `resource` and `numeric`.

`NodeEmitter/PhpObjectSetEmitter` shares `PhpObjectCallDispatchResolver` with the read path, so a class-name target is static however written, and prefixes a bare static member with `$`: a class constant is not assignable (#2907).

Bare host symbols read by **position**, never by probing the class table (ADR 0016). `SymbolResolver::resolveBareHostSymbol()` reads an all-caps name as a global constant; `Domain/Analyzer/BareHostClass` re-reads it as a class wherever only a class can stand (member target in `PhpObjectCallSymbol`, `PhpNewSymbol`, `PhpCallableSymbol`), so `(WP_CLI/log "x")` emits `\WP_CLI::log("x")` whether or not the class loads. `php/NAME` stays a constant everywhere. Do not reintroduce probing: emitted PHP then depends on what the process had autoloaded, and the compiled-code cache freezes whichever ran first.

`Domain/Analyzer/PhpClassLike` is the single class-like existence predicate. It still answers for `:use` alias targets (asserted at compile time), lowercase-initial bare names (`stdClass`), and `Environment/AmbiguousBareHostWarner`, which warns when value position reads a constant that a loadable class shadows.

`Domain/Analyzer/UnresolvedSymbolAdvisor` builds the unresolved-symbol error. A qualified symbol is searched only in its own namespace (alias resolved, plus `BundledSymbolIndex`); an unknown alias names the bundled namespace to require; a bare name from a bundled namespace names the `:require ... :refer`. `BundledSymbolIndex` regex-reads public defs from stdlib sources and never loads a namespace, keeping the error path cheap (#3458).

## Simplification pass

Runs after `ConstantFolder` (in `Domain/Analyzer/TypeAnalyzer/Simplification/`):

- Drops pure non-tail expressions from `(do ...)` via `PureExpressionDetector`.
- Inlines calls at opt level >= 2 via `CallInliner` (purity from `ConstantFolder` for known calls, `SymbolicPurityDetector` for structural checks).
- `^:pure` opts a `defn` into inlining trust (author owns correctness).

**`update` with a literal fn** (`UpdateLiteralFnLowering`, #3322): `InvokeSymbol` lowers an analysed `(update m k (fn [v x ...] body) x ...)` with a literal single-arity `FnNode` into the `let` the runtime `update` runs.

- Gates: opt level >= 2; never on a `:redef` or `:dynamic` `update` (same as `CallInliner`).
- Bindings land in the caller's PHP frame, so only return position outside a `try` (`NodeEnvironment::isWithinTry()`, reset by a fn body). Other positions keep the closure.
- Works on the analysed call: the body is analysed once in its own env (macros see the real `&env`, params untyped); declining builds the ordinary `CallNode`.
- Params become `let` bindings under fresh PHP names (`ParamRenamer`); a nested fn declines, since its captures name the param. `ExpressionContextRebuilder` moves the body's value to expression position.
- The `let` is marked `LetNode::withSplicedFnBodyAfter()`: `ParamTypeInferrer` stops there as at a fn body, `TailCallRewriter` carries the mark, `CallInliner` and `LetSimplifier` leave it alone.
- Body and extra arguments must be fully on the `SpliceableBody` allowlist: literals, local and global reads, collection literals without reader metadata, `php/aget`, `if`/`do`/`let` (not `loop`), nested fns, and calls to PHP operators except `=`/`=&`, a few pure builtins (`max`, `min`, `abs`, ...), keywords, or global Phel fns that are not `^:dynamic`/`^:redef` and whose `__invoke` has no by-ref param (reflected; undefined counts as by-ref). Extra arguments are restricted because the closure captured enclosing locals before they ran. Keep it an allowlist: a denylist kept leaking.
- `recur` to the fn, rest/multi-arity/named fns, tagged params and any return type (declared or inferred) keep the closure.

## Local binding names

`TypeAnalyzer/SpecialForm/LocalBindingName` keeps function parameters and local shadows in `let`/`loop`, `foreach` and `catch` apart from emitter scratch prefixes (`__truthy`, `__or`, `__phel`). Rebinding preserves parameter metadata. Plain generated numeric names and `this` retain their existing mappings.

## Type Tags (inference and canonicalization)

`ParamTypeInferrer` and `ReturnTypeInferrer` graft `:tag` meta onto binding symbols. Contract: propagate only analyzer-published types, never fabricate.

`TagCanonicalizer` rewrites a tag naming a `:use` import to the rooted class where it is declared: `FnSymbol` (params + arg-vector return tag), `AnalyzeBindingsTrait` (`let`/`loop`), `DefSymbol`, `ForeachSymbol`, `DefStructSymbol`, `DefInterfaceSymbol`. Union (list) and intersection (vector) tags are rewritten per member, since `PhpAttributeEmitterTrait` joins them verbatim. Downstream readers (inferrers, emitters, another namespace's caller, an inlined body) resolve through stateless `Shared\TagResolver`, never the active `:use` table. A new form binding a tagged symbol must canonicalize too, or imported tags emit unqualified.

Inferred tags replace AST symbols with metadata-bearing copies. `NodeEnvironment` clones share an object-keyed inferred-type table, so a late result (loop fixpoint, inferred defn param) reaches nodes analyzed earlier. Do not restore in-place `Symbol::withMeta()` mutation.

The emitter side, specialization by tag, is in `.agnostic-ai/rules/module-compiler-emitter.md`.

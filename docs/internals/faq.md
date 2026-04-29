# Internals FAQ

Grouped by reader.

## PHP developer

**Interpreter or compiler?** Compiler. Each top-level form lowers to PHP source, written to a file, then `require`d. No runtime AST walker.

**What does emitted PHP look like?** Cached files under `var/cache/` after one `phel run`, or the `--PHP--` section of any fixture in `tests/php/Integration/Fixtures/`. Regular PHP calling `\Phel::*` and `\phel\core\*`.

**Why `\Phel::addDefinition()` instead of plain functions?** Phel namespaces are runtime values (see [runtime.md](runtime.md)). Single static surface tracks definitions, metadata, reloads. One `require` registers a whole namespace, no per-definition class-loading.

**How does `(my-fn arg)` reach my PHP?** Phel function: compiles to a class with `__invoke`, call site emits `(($lookup))($arg)`. Interop: `(php/-> $obj method arg)` and friends compile to literal `$obj->method($arg)`. See [special-forms.md](special-forms.md).

**Xdebug, Psalm, PHPStan?** Yes, on the generated PHP. Source maps point traces back to `.phel`. `composer test-quality` runs Psalm + PHPStan on `src/php/`.

**Call cost?** Function: one `__invoke` plus arg unpacking. Negligible. Collections: trie-backed, O(log32 n) with small constants. See [benchmarks.md](benchmarks.md).

## Coming from Clojure

**Missing features.** No agents, no STM/refs (use `Variable` + `swap!`), no `core.async` (Phel has fibers + futures via `Fiber/`, see [async-guide.md](../async-guide.md)), no protocols (use `definterface`), no records (use `defstruct`).

**Real persistent collections?** Yes. `PersistentVector` (32-way trie), `PersistentHashMap` (HAMT), `LazySeq` (per-element realisation). Under `Lang/Collections/`.

**Hygienic macros?** Quasiquote namespace-qualifies symbols at read time. `x#` inside a quasiquote expands to a fresh gensym consistent within that quasiquote. See [macros.md](macros.md).

**REPL? nREPL?** Both. `phel repl` interactive; `phel nrepl` over bencode/TCP. See [nrepl-guide.md](../nrepl-guide.md).

**`recur` and tail calls.** Loop body becomes `while (true) { ... }` rebinding parameters. No stack growth. PHP has no TCO; `recur` does not need it.

## Modifying the compiler

**Where to start.** `src/php/Compiler/CLAUDE.md` for facade overview, then [compiler.md](compiler.md). Most edits hit one of:

- `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`
- `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/`
- `Compiler/Domain/Reader/`
- `Compiler/Application/Lexer.php`

**Inspect a single phase.** Facade hooks:

```php
$tokens = $facade->lexString('(print "hi")');
$tree   = $facade->parseAll($tokens);
$read   = $facade->read($tree->getChildren()[0]);
$ast    = $facade->analyze($read->getAst(), NodeEnvironment::empty());
$emit   = $facade->compile('(print "hi")', new CompileOptions());
```

**Add a special form.** [special-forms.md](special-forms.md#adding-one). Most common miss: dispatch wiring in `AnalyzePersistentList`.

**Add a core function.** Pure Phel: `src/phel/core/...` with `:doc`, `:see-also`, `:example`. Run `composer test-core`. Needs PHP: thin wrapper under `src/phel/...` calling a PHP helper, run `composer test-compiler` + `composer test-core`.

**`NodeEnvironment` context.** `Expression`, `Statement`, `Return`. Analyzer picks based on position (function body last form: `Return`; `if` branch in expression position: `Expression`). Wrong PHP output? 90% of the time wrong context, not the emitter. Helpers: `withReturnContext()`, `withStatementContext()`, `withExpressionContext()`.

**Broken integration fixture.** Update only if new PHP is intentional. Verify by hand first. The `fixture-reviewer` agent (`.claude/agents/`) audits drift.

**Run one test.**

```bash
./vendor/bin/phpunit --filter=test_method_name
./vendor/bin/phpunit tests/php/path/to/Test.php
./bin/phel test tests/phel/path/to/test.phel
```

## Building tools around Phel

**Public API.** Every `*Facade.php` under `src/php/*/`, with `*FacadeInterface.php` for typing. `CLAUDE.md` per module documents it. Tooling-relevant: `CompilerFacade`, `ApiFacade`, `LintFacade`, `FormatterFacade`. `Lsp/` and `Nrepl/` are worked examples.

**Run compiler without CLI.** Bootstrap Gacela, fetch `CompilerFacade`, call `compile()` / `eval()`. `tests/php/Integration/` does this.

**Source locations.** Every readable form, AST node, and emitted line carries a `SourceLocation` (file + line + col). Parse tree node: ask first/last token. AST node: stored in constructor. Emitted PHP: reverse via source map in `Compiler/Domain/Emitter/OutputEmitter/SourceMap/`.

**Macroexpand from PHP.** `CompilerFacade::macroexpand1($form)`, `macroexpand($form)`. Returns Phel forms. Same path nREPL uses.

**Introspect a namespace at runtime.** `ApiFacade`: symbol search, doc, `:see-also`, source locations. `Lang\Registry` is the store but private.

**Cache invalidation.** Keyed by source hash + Phel version. Phel bump busts automatically. Local generated-code change: `phel cache:reset` or `rm -rf var/cache/`.

## Bug hunting

**"Cannot resolve symbol X".** `AnalyzeSymbol` checks locals → current ns globals → `use` aliases → `phel\core`. Miss: `SymbolSuggestionProvider` builds a suggestion + throws with `SourceLocation`. Common cause: missing `(require ...)`.

**"Type X cannot be coerced" or unexpected `nil`.** Run `(macroexpand-1 'form)`. Often a macro expansion surprise.

**Compiles fine, runtime is weird.** Read the cached `.php`. Surprising emit = analyzer bug (wrong context, wrong recur frame, wrong locals). Emitter just transcribes.

**`Lang/` change breaks ten modules.** Working as designed. `composer test` before push. Faster signal: `composer test-compiler`.

## Source map

| Question | File |
|----------|------|
| Special forms list | `src/php/Lang/Symbol.php` (`NAME_*`) |
| Special form analysis | `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/` |
| Node emission | `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/` |
| Reader macros | `Compiler/Domain/Reader/ExpressionReader/` |
| Quasiquote rewrite | `Compiler/Domain/Reader/QuasiquoteTransformer.php` |
| Macro expansion | `Compiler/Application/MacroExpander.php` |
| AST nodes | `Compiler/Domain/Analyzer/Ast/` |
| Module API | each `*Facade.php` + `CLAUDE.md` |

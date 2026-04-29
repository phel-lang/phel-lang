# Internals FAQ

Common questions about how Phel works internally, grouped by who is asking. If you only have five minutes, skim the headings.

---

## I'm a PHP developer

### Is Phel an interpreter or does it actually compile?

It compiles. Each top-level Phel form is lowered to PHP source code, written to a file, and `require`d. There is no runtime interpreter walking AST nodes.

### What does the generated PHP look like?

Open any cached file under `var/cache/` after running `phel run` once, or look at `tests/php/Integration/Fixtures/**/*.test` — the `--PHP--` section is exactly what the compiler emits. It is regular PHP that calls into `\Phel::*` and `\phel\core\*`.

### Why does compiled code go through `\Phel::addDefinition()` instead of just defining functions?

Phel namespaces are first-class runtime values (see [runtime.md](runtime.md)). The static facade gives the runtime a single place to track definitions, metadata, and reloads. It also means a single PHP `require` is enough to register everything in a namespace — no class-loading dance per definition.

### How does `(my-fn arg)` reach my PHP code?

If `my-fn` is a Phel function: it compiles to a small PHP class with `__invoke`, and the call site emits `(($globalLookup))($arg)`.

If `my-fn` is PHP code, you wrote `(php/-> $obj method arg)` or one of the `php/*` interop forms. Those compile to literal `$obj->method($arg)` — see the PHP-interop rows in [special-forms.md](special-forms.md).

### Can I drop into PHP debug tooling (Xdebug, Psalm, PHPStan)?

Yes, on the *generated* PHP. The compiler writes source maps so stack traces point back at the original `.phel` file. Static analysers run against the cached PHP just fine; `composer test-quality` includes Psalm and PHPStan over `src/php/`.

### What's the cost of a Phel call vs. a PHP call?

For functions: one method call (`__invoke`) plus argument unpacking. Negligible.

For collections: persistent maps and vectors are tries — operations are O(log32 n) but with small constants. See [internals/benchmarks.md](benchmarks.md).

---

## I'm coming from Clojure

### Which Clojure features are missing?

The big ones: no agents, no STM/refs (use `Variable` and `swap!`), no `core.async` (Phel has fibers + futures via `Fiber/`, see [async-guide.md](../async-guide.md)), no protocols (Phel has interfaces via `definterface`), no records (Phel has structs via `defstruct`).

### Are persistent collections the real thing or a thin wrapper over PHP arrays?

Real persistent data structures with structural sharing. `PersistentVector` is a 32-way trie, `PersistentHashMap` is a HAMT, `LazySeq` realises one element at a time. See `Lang/Collections/`.

### How do macros work? Are they hygienic?

Standard Clojure-style: quasiquote namespaces symbols at read time, and `x#` inside a quasiquote becomes an auto-gensym consistent within that quasiquote. See [macros.md](macros.md). Hygiene is the same model as Clojure — sufficient for almost everything, not airtight if you go out of your way to break it.

### Is there a REPL? An nREPL?

Both. `phel repl` is the interactive REPL; `phel nrepl` speaks bencode/TCP for editor integrations (CIDER-style). See [nrepl-guide.md](../nrepl-guide.md).

### Does `recur` actually do tail-call elimination on the PHP VM?

It compiles the loop body to a `while (true) { ... }` and rebinds parameters in place — no stack growth. PHP itself has no TCO, but `recur` doesn't need it.

---

## I'm modifying the compiler

### Where do I start?

`src/php/Compiler/CLAUDE.md` for the facade-level overview. Then [compiler.md](compiler.md) for the pipeline. Then the module you actually want to touch — most changes live in one of:

- `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/` — special forms
- `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/` — PHP code generation
- `Compiler/Domain/Reader/` — reader macros and quasiquote
- `Compiler/Application/Lexer.php` — tokenisation

### How do I see what a form lexes/parses/reads/analyses to?

Use the facade hooks on `CompilerFacade`:

```php
$tokens   = $facade->lexString('(print "hi")');
$tree     = $facade->parseAll($tokens);
$readResult = $facade->read($tree->getChildren()[0]);
$ast      = $facade->analyze($readResult->getAst(), NodeEnvironment::empty());
$emit     = $facade->compile('(print "hi")', new CompileOptions());
```

For a one-liner, the integration fixture format under `tests/php/Integration/Fixtures/` documents end-to-end expectations.

### How do I add a new special form?

Five-step recipe in [special-forms.md](special-forms.md#adding-a-new-special-form). Skipping the dispatch wiring in `AnalyzePersistentList` is the most common mistake.

### How do I add a new core function?

If it can be written in Phel: add it to `src/phel/core/...`, with `:doc`, `:see-also`, `:example` metadata. Run `composer test-core`.

If it must be in PHP (interop with a system facility): add it under `src/phel/...` as a thin wrapper that calls into `\Phel::php(...)` or a dedicated PHP helper, then run both `composer test-compiler` and `composer test-core`.

### What's the rule for `NodeEnvironment` context?

Three contexts: `Expression`, `Statement`, `Return`. The analyzer chooses based on where the form sits — a function body's last form is `Return`, an `if` branch in expression position is `Expression`, etc. The emitter trusts what was written. If you produce wrong PHP (extra/missing `return`, value where statement expected), 90% of the time the bug is the context, not the emitter.

Helpers on `NodeEnvironment`: `withReturnContext()`, `withStatementContext()`, `withExpressionContext()`.

### My change broke an integration fixture. Do I update the fixture?

Only if the new PHP is intentionally what you want. Read the old `--PHP--`, run the form by hand, confirm the new output is correct, then update. The `fixture-reviewer` agent (see `.claude/agents/`) is built for this audit pass.

### How do I run just my failing test?

```bash
./vendor/bin/phpunit --filter=test_method_name
./vendor/bin/phpunit tests/php/path/to/Test.php
./bin/phel test tests/phel/path/to/test.phel
```

---

## I'm building a tool around Phel

### What's the public API?

Every facade in `src/php/*/`. Each one has a `*Facade.php` plus a `*FacadeInterface.php` you should depend on. `CLAUDE.md` files document them.

For language tooling specifically: `CompilerFacade`, `ApiFacade`, `LintFacade`, `FormatterFacade`. The LSP and nREPL servers under `Lsp/` and `Nrepl/` are themselves consumers of these — they're the worked examples for "build a tool".

### Can I run the compiler without the CLI?

Yes. Bootstrap Gacela, fetch `CompilerFacade`, call `compile()` / `eval()`. `tests/php/Integration/` does exactly this.

### How do I get source locations for diagnostics?

Every readable form, every AST node, and every emitted line preserves a `SourceLocation` (file + line + column). For a parse tree node, ask its first/last token. For an AST node, the constructor stored one. For emitted PHP, the source map under `Compiler/Domain/Emitter/OutputEmitter/SourceMap/` reverses the mapping.

### How do I expand a macro from PHP without compiling?

`CompilerFacade::macroexpand1($form)` and `CompilerFacade::macroexpand($form)` return Phel forms (not PHP). That is the same path the nREPL macroexpand op uses.

### How do I introspect a Phel namespace at runtime?

`ApiFacade` is the supported surface — symbol search, doc strings, `:see-also` links, source locations. `Lang\Registry` is the underlying store, but treat it as private.

### Is the cache safe across PHP versions / Phel versions?

The cache is keyed by Phel source hash plus Phel version. Bumping Phel busts it automatically. If you change generated-code shape during development, run `phel cache:reset` (or just delete `var/cache/`).

---

## I'm investigating a bug

### "Cannot resolve symbol X" — what does the analyzer actually do?

`AnalyzeSymbol` checks: locals (lexical scope), then the current namespace's globals, then `use`d aliases, then `phel\core`. If still nothing, it builds a suggestion via `SymbolSuggestionProvider` and throws with a `SourceLocation`.

Common cause: you `def`d in one file but didn't `(require ...)` from the consuming namespace.

### "Type X cannot be coerced" or unexpected `nil`

Run `(macroexpand-1 'form)` first. Many "weird type" bugs are macro expansions that aren't doing what you think.

### Compilation succeeds but the runtime behaves oddly

Look at the cached `.php`. If the emitter produced something surprising, the bug is almost always in the analyzer (wrong context, wrong recur frame, wrong locals map). The emitter just transcribes.

### A change in `Lang/` breaks ten other modules

That's the contract working as intended — `Lang/` is foundational. Run `composer test` (everything) at least once before you push. For a faster signal, `composer test-compiler` covers most consumers.

---

## Where to look in the source

| Question | File |
|----------|------|
| What special forms exist? | `src/php/Lang/Symbol.php` (`NAME_*` constants) |
| How is a special form analysed? | `src/php/Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/` |
| How is a node emitted? | `src/php/Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/` |
| How is a reader macro handled? | `src/php/Compiler/Domain/Reader/ExpressionReader/` |
| How does quasiquote rewrite? | `src/php/Compiler/Domain/Reader/QuasiquoteTransformer.php` |
| How are macros expanded? | `src/php/Compiler/Application/MacroExpander.php` |
| What does an AST node carry? | `src/php/Compiler/Domain/Analyzer/Ast/` (one file per node) |
| What does each module expose? | the `*Facade.php` and `CLAUDE.md` in that module |

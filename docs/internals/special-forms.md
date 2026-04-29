# Special Forms

A special form is a list whose head symbol the analyzer recognises and dispatches to a dedicated handler. Anything that is not a special form, a macro, or a literal collection is an ordinary function call. Macros expand into special forms and calls — special forms are the bottom of the stack.

## How dispatch works

`Compiler/Domain/Analyzer/TypeAnalyzer/AnalyzePersistentList.php` reads the first element of the list. If it is a `Symbol` whose name matches one of the `Symbol::NAME_*` constants in `src/php/Lang/Symbol.php`, the list is treated as a special form and routed to a `SpecialFormAnalyzerInterface` implementation in `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`. Otherwise the list is a call and goes to `InvokeSymbol`.

The mapping is one big `match` on the symbol name (excerpt from `AnalyzePersistentList::analyzeSpecialForm()`):

```php
match ($symbolName) {
    Symbol::NAME_DEF        => new DefSymbol($this->analyzer),
    Symbol::NAME_FN         => new FnSymbol($this->analyzer, $this->assertsEnabled),
    Symbol::NAME_IF         => new IfSymbol($this->analyzer),
    Symbol::NAME_LET        => new LetSymbol($this->analyzer, ...),
    Symbol::NAME_LOOP       => new LoopSymbol($this->analyzer, ...),
    Symbol::NAME_RECUR      => new RecurSymbol($this->analyzer),
    // ...
};
```

Each handler returns one `AbstractNode` subclass (`DefNode`, `FnNode`, `IfNode`, …). The emitter has a one-to-one mapping from node type to `*Emitter.php` under `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/`.

## The list

Most users only see ten or so of these — the rest are either implementation details (`*` suffix means "use the macro, not this") or PHP-interop primitives.

### Core

| Form | Symbol const | Analyzer | AST node | Notes |
|------|--------------|----------|----------|-------|
| `def` | `NAME_DEF` | `DefSymbol` | `DefNode` | Top-level binding; registers in `GlobalEnvironment` and `Registry`. |
| `fn` | `NAME_FN` | `FnSymbol` | `FnNode` / `MultiFnNode` | Compiled to a PHP class implementing `__invoke`. |
| `if` | `NAME_IF` | `IfSymbol` | `IfNode` | Three-arg form; `nil` and `false` are falsy. |
| `let` | `NAME_LET` | `LetSymbol` | `LetNode` | Sequential bindings; supports destructuring. |
| `loop` | `NAME_LOOP` | `LoopSymbol` | `LetNode` (with recur frame) | Establishes a `recur` target. |
| `recur` | `NAME_RECUR` | `RecurSymbol` | `RecurNode` | Tail-call rebinds the innermost frame. |
| `do` | `NAME_DO` | `DoSymbol` | `DoNode` | Sequence of forms; value is the last. |
| `quote` | `NAME_QUOTE` | `QuoteSymbol` | `QuoteNode` | Returns its argument unevaluated. |
| `apply` | `NAME_APPLY` | `ApplySymbol` | `ApplyNode` | Calls a function with a sequence of args. |
| `foreach` | `NAME_FOREACH` | `ForeachSymbol` | `ForeachNode` | Iteration with side effects, `nil` result. |
| `try` / `catch` / `finally` | `NAME_TRY` | `TrySymbol` | `TryNode`, `CatchNode` | `catch` and `finally` only valid inside `try`. |
| `throw` | `NAME_THROW` | `ThrowSymbol` | `ThrowNode` | Throws any `\Throwable`. |
| `set-var` | `NAME_SET_VAR` | `SetVarSymbol` | `SetVarNode` | Assigns a `Variable` (rare; prefer `swap!`). |

### Namespacing

| Form | Symbol const | Analyzer | AST node |
|------|--------------|----------|----------|
| `ns` | `NAME_NS` | `NsSymbol` | `NsNode` |
| `in-ns` | `NAME_IN_NS` | `InNsSymbol` | `InNsNode` |
| `use` | `NAME_USE` | `UseSymbol` | `UseNode` |
| `load` | `NAME_LOAD` | `LoadSymbol` | `LoadNode` |

### Type definitions (low-level forms)

These have a trailing `*` and are not meant to be written directly — public macros (`defstruct`, `definterface`, `defexception`, `reify`) expand into them.

| Form | Symbol const | Analyzer | AST node |
|------|--------------|----------|----------|
| `defstruct*` | `NAME_DEF_STRUCT` | `DefStructSymbol` | `DefStructNode` |
| `definterface*` | `NAME_DEF_INTERFACE` | `DefInterfaceSymbol` | `DefInterfaceNode` |
| `defexception*` | `NAME_DEF_EXCEPTION` | `DefExceptionSymbol` | `DefExceptionNode` |
| `reify*` | `NAME_REIFY` | `ReifySymbol` | `ReifyNode` |

### PHP interop

These map almost directly to PHP syntax. If a function would need to call into PHP at the syntactic level (e.g. `new Foo()` or `$obj->bar`), it goes through one of these.

| Form | Symbol const | What it emits |
|------|--------------|---------------|
| `php/new` (alias `new`) | `NAME_PHP_NEW` / `NAME_NEW` | `new Foo(...)` |
| `php/->` | `NAME_PHP_OBJECT_CALL` | `$obj->method(...)` or `$obj->property` |
| `php/::` | `NAME_PHP_OBJECT_STATIC_CALL` | `Foo::method(...)` or `Foo::CONST` |
| `php/oset` | `NAME_PHP_OBJECT_SET` | `$obj->prop = $v` |
| `php/aget` | `NAME_PHP_ARRAY_GET` | `$arr[$k]` |
| `php/aset` | `NAME_PHP_ARRAY_SET` | `$arr[$k] = $v` |
| `php/apush` | `NAME_PHP_ARRAY_PUSH` | `$arr[] = $v` |
| `php/aunset` | `NAME_PHP_ARRAY_UNSET` | `unset($arr[$k])` |
| `php/aget-in`, `php/aset-in`, `php/apush-in`, `php/aunset-in` | … | nested-path variants |

## What is *not* a special form

- `if-let`, `when`, `cond`, `case`, `match`, `->`, `->>`, `loop` body destructuring → **macros** in `phel\core`. Expand them with `(macroexpand-1 'form)` to see what they reduce to.
- `defn`, `defmacro`, `defstruct`, `definterface`, `defexception`, `reify` → **macros** that wrap the `*` forms above.
- `+`, `-`, `=`, `map`, `filter`, … → **functions** in `phel\core`.

When in doubt: open a REPL, run `(macroexpand-1 'your-form)`, and see what comes back.

## Adding a new special form

This is a five-touch change. Skip a step and the analyzer or emitter will throw at compile time.

1. **Reserve the symbol name.** Add a `public const string NAME_FOO = 'foo';` to `src/php/Lang/Symbol.php`.
2. **Create the AST node.** Add `FooNode extends AbstractNode` under `Compiler/Domain/Analyzer/Ast/`. Constructor takes a `NodeEnvironment` plus whatever child fields you need.
3. **Create the analyzer handler.** Add `FooSymbol implements SpecialFormAnalyzerInterface` under `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`. Validate arity and shape; recurse into children with `$this->analyzer->analyze(...)`; return a `FooNode`.
4. **Wire dispatch.** Add a branch to the `match` in `Compiler/Domain/Analyzer/TypeAnalyzer/AnalyzePersistentList.php`.
5. **Create the emitter.** Add `FooEmitter` under `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/`, register it in `NodeEmitterFactory`. Use the node's `NodeEnvironment` context to decide between expression / statement / return output.

Then add an integration fixture under `tests/php/Integration/Fixtures/Foo/foo-basic.test` (`--PHEL--` / `--PHP--` sections — see `.claude/rules/integration-tests.md`) and at least one PHPUnit test for the analyzer.

## See also

- [compiler.md](compiler.md) — the pipeline that wraps this
- [macros.md](macros.md) — how user-defined macros expand to special forms
- `src/php/Compiler/CLAUDE.md` — module facade reference

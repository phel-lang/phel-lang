# Special Forms

List with a recognised head symbol routed to a dedicated analyzer. Anything else (not a special form, not a macro, not a literal collection) is a function call. Macros expand into special forms: special forms are the bottom.

## Dispatch

`Compiler/Domain/Analyzer/TypeAnalyzer/AnalyzePersistentList.php` matches the head against `Symbol::NAME_*` constants in `src/php/Lang/Symbol.php`. Match → `SpecialFormAnalyzerInterface` impl in `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`. Miss → `InvokeSymbol`.

```php
match ($symbolName) {
    Symbol::NAME_DEF   => new DefSymbol($this->analyzer),
    Symbol::NAME_FN    => new FnSymbol($this->analyzer, $this->assertsEnabled),
    Symbol::NAME_IF    => new IfSymbol($this->analyzer),
    Symbol::NAME_LET   => new LetSymbol($this->analyzer, ...),
    Symbol::NAME_LOOP  => new LoopSymbol($this->analyzer, ...),
    Symbol::NAME_RECUR => new RecurSymbol($this->analyzer),
    // ...
};
```

Each handler returns one `AbstractNode`. Emitter has 1:1 mapping to `*Emitter.php` under `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/`.

## Core

| Form | Const | Handler | Node | Notes |
|------|-------|---------|------|-------|
| `def` | `NAME_DEF` | `DefSymbol` | `DefNode` | Top-level binding; registers in `GlobalEnvironment` + `Registry`. |
| `fn` | `NAME_FN` | `FnSymbol` | `FnNode` / `MultiFnNode` | Compiles to PHP class with `__invoke`. |
| `if` | `NAME_IF` | `IfSymbol` | `IfNode` | Three-arg; `nil`/`false` falsy. |
| `let` | `NAME_LET` | `LetSymbol` | `LetNode` | Sequential bindings + destructuring. |
| `loop` | `NAME_LOOP` | `LoopSymbol` | `LetNode` + recur frame | `recur` target. |
| `recur` | `NAME_RECUR` | `RecurSymbol` | `RecurNode` | Tail-rebind innermost frame. |
| `do` | `NAME_DO` | `DoSymbol` | `DoNode` | Sequence; value = last. |
| `quote` | `NAME_QUOTE` | `QuoteSymbol` | `QuoteNode` | Returns arg unevaluated. |
| `apply` | `NAME_APPLY` | `ApplySymbol` | `ApplyNode` | Call fn with seq of args. |
| `foreach` | `NAME_FOREACH` | `ForeachSymbol` | `ForeachNode` | Side-effect iteration; `nil` result. |
| `try` / `catch` / `finally` | `NAME_TRY` | `TrySymbol` | `TryNode`, `CatchNode` | `catch`/`finally` only inside `try`. |
| `throw` | `NAME_THROW` | `ThrowSymbol` | `ThrowNode` | Any `\Throwable`. |
| `set-var` | `NAME_SET_VAR` | `SetVarSymbol` | `SetVarNode` | Rare; prefer `swap!`. |

## Namespacing

| Form | Const | Handler | Node |
|------|-------|---------|------|
| `ns` | `NAME_NS` | `NsSymbol` | `NsNode` |
| `in-ns` | `NAME_IN_NS` | `InNsSymbol` | `InNsNode` |
| `use` | `NAME_USE` | `UseSymbol` | `UseNode` |
| `load` | `NAME_LOAD` | `LoadSymbol` | `LoadNode` |

## Type definitions (low-level)

Trailing `*` = not user-facing. Macros `defstruct`, `definterface`, `defexception`, `reify` expand into these.

| Form | Const | Handler | Node |
|------|-------|---------|------|
| `defstruct*` | `NAME_DEF_STRUCT` | `DefStructSymbol` | `DefStructNode` |
| `definterface*` | `NAME_DEF_INTERFACE` | `DefInterfaceSymbol` | `DefInterfaceNode` |
| `defexception*` | `NAME_DEF_EXCEPTION` | `DefExceptionSymbol` | `DefExceptionNode` |
| `reify*` | `NAME_REIFY` | `ReifySymbol` | `ReifyNode` |

## PHP interop

| Form | Const | Emits |
|------|-------|-------|
| `php/new` (`new`) | `NAME_PHP_NEW` / `NAME_NEW` | `new Foo(...)` |
| `php/->` | `NAME_PHP_OBJECT_CALL` | `$obj->method(...)` / `$obj->prop` |
| `php/::` | `NAME_PHP_OBJECT_STATIC_CALL` | `Foo::method(...)` / `Foo::CONST` |
| `php/oset` | `NAME_PHP_OBJECT_SET` | `$obj->prop = $v` |
| `php/aget` | `NAME_PHP_ARRAY_GET` | `$arr[$k]` |
| `php/aset` | `NAME_PHP_ARRAY_SET` | `$arr[$k] = $v` |
| `php/apush` | `NAME_PHP_ARRAY_PUSH` | `$arr[] = $v` |
| `php/aunset` | `NAME_PHP_ARRAY_UNSET` | `unset($arr[$k])` |
| `php/aget-in` / `aset-in` / `apush-in` / `aunset-in` | … | nested-path variants |

## Not special forms

- `if-let`, `when`, `cond`, `case`, `match`, `->`, `->>` → macros in `phel\core`
- `defn`, `defmacro`, `defstruct`, `definterface`, `defexception`, `reify` → macros over `*` forms
- `+`, `-`, `=`, `map`, `filter`, … → functions in `phel\core`

`(macroexpand-1 'form)` to see the truth.

## Adding one

5 touches. Skip → throws at compile time.

1. `public const string NAME_FOO = 'foo';` in `src/php/Lang/Symbol.php`
2. `FooNode extends AbstractNode` in `Compiler/Domain/Analyzer/Ast/`
3. `FooSymbol implements SpecialFormAnalyzerInterface` in `Compiler/Domain/Analyzer/TypeAnalyzer/SpecialForm/`: validate, recurse via `$this->analyzer->analyze(...)`, return `FooNode`
4. Branch in `AnalyzePersistentList`'s `match`
5. `FooEmitter` in `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/`, register in `NodeEmitterFactory`. Honor `NodeEnvironment` context

Add fixture `tests/php/Integration/Fixtures/Foo/foo-basic.test` + analyzer PHPUnit test.

## See also

[compiler.md](compiler.md), [macros.md](macros.md), `src/php/Compiler/CLAUDE.md`

# Compiler Internals

How the Phel compiler turns source code into running PHP. The compiler is a six-stage pipeline; each stage consumes only the output of the previous stage. Source locations propagate through every stage so error messages can point back at the original `.phel` file.

## Pipeline at a glance

| Stage | Class | Input | Output |
|-------|-------|-------|--------|
| Lexer | `Compiler/Application/Lexer.php` | Phel source `string` | `TokenStream` |
| Parser | `Compiler/Application/Parser.php` | `TokenStream` | parse tree (`NodeInterface`) |
| Reader | `Compiler/Application/Reader.php` | parse tree | `ReaderResult` (Phel data) |
| Analyzer | `Compiler/Application/Analyzer.php` | Phel data | `AbstractNode` (AST) |
| Emitter | `Compiler/Domain/Emitter/OutputEmitter.php` | `AbstractNode` | PHP `string` |
| Evaluator | `Compiler/Domain/Evaluator/` (`InMemoryEvaluator`, `RequireEvaluator`) | PHP `string` | runtime values / side effects |

The public entry points live on `CompilerFacade`:

- `compile()` / `compileForCache()` — full pipeline, returns `EmitterResult`
- `compileForm()` — same, but starts from already-read Phel data
- `eval()` / `evalForm()` — compile and execute
- `lexString()` / `parseNext()` / `parseAll()` / `read()` / `analyze()` — single-stage hooks for tools (LSP, nREPL, linter)

The example below traces `(print "hi")` through every stage.

## 1. Lexer

`Compiler/Application/Lexer.php` walks the source string and emits a `TokenStream` of `Token` objects. Each `Token` carries:

- `type` — one of the `T_*` constants on `Token`
- `code` — the raw lexeme
- start/end `SourceLocation` — file, line, column

Whitespace and comments become real tokens (`T_WHITESPACE`, `T_COMMENT`) so the parser can preserve formatting for tools that need it (formatter, linter).

```text
T_OPEN_PARENTHESIS  "("
T_ATOM              "print"
T_WHITESPACE        " "
T_STRING            "\"hi\""
T_CLOSE_PARENTHESIS ")"
T_EOF               ""
```

## 2. Parser

`Compiler/Application/Parser.php` consumes a `TokenStream` and produces a parse tree of `NodeInterface` values (`Compiler/Domain/Parser/ParserNode/`). The tree retains every token, including trivia, so the formatter and the linter can rewrite source without losing comments.

Specialised sub-parsers live under `Compiler/Domain/Parser/ExpressionParser/` and are wired together by `ExpressionParserFactory`. Examples: `ListParser`, `VectorParser`, `MapParser`, `MetaParser`, `QuoteParser`.

```text
ListNode
├── SymbolNode("print")
├── WhitespaceNode(" ")
└── StringNode("\"hi\"")
```

## 3. Reader

`Compiler/Application/Reader.php` turns the parse tree into Phel data — symbols, keywords, lists, vectors, maps, sets — backed by the persistent collections in `Lang/Collections/`. Trivia is dropped here. The result is a `ReaderResult` that keeps a reference to the original snippet for error reporting.

The reader also expands reader macros: `'x` → `(quote x)`, `` `x `` → quasiquote, `~x` → unquote, `~@x` → unquote-splicing, `#(...)` → anonymous fn, `#inst`, `#regex`, `#php`, custom `#` tag handlers (`Lang/TagHandlers/`, `Lang/TagRegistry.php`).

Quasiquote is non-trivial: `Compiler/Domain/Reader/QuasiquoteTransformer.php` rewrites `` `(...) `` into explicit `concat`/`list` calls, with auto-gensym (`x#`) handled by `Compiler/Domain/Reader/GensymContext.php`.

```lisp
(print "hi")
```

## 4. Analyzer

`Compiler/Application/Analyzer.php` validates Phel data and produces a typed AST of `AbstractNode` subclasses (`Compiler/Domain/Analyzer/Ast/`). Two things happen in parallel:

1. **Side effects on the global environment** (`GlobalEnvironment`): top-level `def`, `defmacro`, `def-struct`, `ns`, and friends register names so subsequent forms in the same compile unit can resolve them.
2. **Tree construction**: each form maps to one `*Node` (`DefNode`, `FnNode`, `LetNode`, `IfNode`, `CallNode`, …).

Dispatch by Phel value type happens in `Compiler/Domain/Analyzer/TypeAnalyzer/`:

- `AnalyzeLiteral` — strings, numbers, booleans, `nil`, keywords
- `AnalyzeSymbol` — local lookup, then global, then suggestion-driven error
- `AnalyzePersistentList` — first decides if the head is a special form (see [special forms](special-forms.md)), then dispatches to a `SpecialForm/*Symbol` handler or falls back to `InvokeSymbol` for ordinary calls
- `AnalyzePersistentVector` / `AnalyzePersistentMap` / `AnalyzePersistentSet` — collection literals, recursing into children

Every `AbstractNode` carries a `NodeEnvironment` (`Compiler/Domain/Analyzer/Environment/NodeEnvironment.php`) describing:

- **Context**: `Expression`, `Statement`, or `Return` — controls whether the emitter must produce a value, a statement, or a `return`
- **Locals** + **shadowed names** — tracked via `withMergedLocals`, `withShadowedLocal`
- **Recur frame** — the innermost `loop`/`fn` that `recur` will jump to

Wrong context = wrong PHP. The emitter trusts what the analyzer wrote.

```text
CallNode (context=Statement)
├── fn:    GlobalVarNode("phel\\core/print")
└── args:  [LiteralNode("hi")]
```

## 5. Emitter

`Compiler/Domain/Emitter/OutputEmitter.php` walks the AST and writes PHP. Per-node logic lives in `Compiler/Domain/Emitter/OutputEmitter/NodeEmitter/` — one emitter per AST node type (`DefEmitter`, `FnAsClassEmitter`, `LetEmitter`, `IfEmitter`, `CallEmitter`, …). Unknown node types must throw, never silently no-op.

Two responsibilities worth knowing:

- **Munge** (`Compiler/Application/Munge.php`) — Phel symbols allow characters PHP forbids. `my-fn?` becomes `my_fn_QMARK_`, `+` becomes `_PLUS_`, `-` becomes `_`, etc. The same algorithm is used by `Lang/Collections/Struct/StructKeyEncoder` so structs and emitted code agree on names.
- **Source maps** (`Compiler/Domain/Emitter/OutputEmitter/SourceMap/`) — every emitted line can be mapped back to the originating Phel `SourceLocation`, used by error reporting and the LSP.

The emitter also has two modes (`EmitMode`): `STATEMENT` for `eval` and the REPL (no leading `<?php`), `FILE` for cached compilation (full file with namespace bootstrap). `FileEmitter` orchestrates a multi-form file; `StatementEmitter` does a single form.

```php
\phel\core\print_("hi");
```

## 6. Evaluator

The evaluator runs the emitted PHP. Two implementations:

- `RequireEvaluator` (`Compiler/Domain/Evaluator/RequireEvaluator.php`) — writes the code to a temp file and `require`s it. This is the production path: `require` triggers the autoloader and respects opcache, which is what makes hot reload + cache reuse fast.
- `InMemoryEvaluator` — `eval()`s the string. Used in tests to avoid touching the filesystem.

Top-level forms have side effects at compile time: a `def` registers a definition before later forms see it; a `defmacro` is *available immediately* to expand subsequent forms in the same file. That is why each top-level form takes a full lex → … → eval round-trip, not the whole file at once. See `Compiler/Application/CodeCompiler.php`.

```text
hi
```

## Where to look next

- [Special forms](special-forms.md) — full list and how dispatch works
- [Macros](macros.md) — `macroexpand`, gensym, quasiquote
- [Architecture](architecture.md) — module boundaries and Gacela layout
- [Runtime](runtime.md) — persistent collections and the `Registry`
- [Internals FAQ](faq.md) — common questions from different angles
- `src/php/Compiler/CLAUDE.md` — facade-level cheat sheet for agents

# Compiler

Seven-stage pipeline. Each stage consumes only the previous stage's output. Source locations propagate end-to-end.

| Stage | Class | In | Out |
|-------|-------|----|-----|
| Lexer | `Application/Lexer.php` | `string` | `TokenStream` |
| Parser | `Application/Parser.php` | `TokenStream` | parse tree (`NodeInterface`) |
| Reader | `Application/Reader.php` | parse tree | `ReaderResult` (Phel data) |
| Analyzer | `Application/Analyzer.php` | Phel data | `AbstractNode` |
| Simplifier | `Domain/Analyzer/TypeAnalyzer/Simplification/` | `AbstractNode` | `AbstractNode` (simplified) |
| Emitter | `Domain/Emitter/OutputEmitter.php` | `AbstractNode` | PHP `string` |
| Evaluator | `Domain/Evaluator/` | PHP `string` | values / side effects |

Paths are relative to `src/php/Compiler/`.

## Public entry points (`CompilerFacade`)

- `compile()` / `compileForCache()`: full pipeline to `EmitterResult`
- `compileForm()`: start from already-read Phel data
- `eval()` / `evalForm()`: compile + execute
- `lexString()` / `parseAll()` / `read()` / `analyze()`: single-stage hooks for LSP, nREPL, linter

## Tracing `(print "hi")`

**Lexer**: `Token` per lexeme with type, code, `SourceLocation`. Trivia kept (`T_WHITESPACE`, `T_COMMENT`) for formatter/linter.

```text
T_OPEN_PARENTHESIS  "("
T_ATOM              "print"
T_WHITESPACE        " "
T_STRING            "\"hi\""
T_CLOSE_PARENTHESIS ")"
```

**Parser**: sub-parsers under `Domain/Parser/ExpressionParser/` (`ListParser`, `AtomParser`, `CharParser`, `StringParser`, `MetaParser`, `QuoteParser`, `RegexParser`, ...) wired by `ExpressionParserFactory`. Tree retains trivia.

```text
ListNode → SymbolNode("print"), WhitespaceNode, StringNode("\"hi\"")
```

**Reader**: parse tree to Phel data backed by `Lang/Collections/`. Drops trivia. Expands reader macros: `'x`, `` `x ``, `~x`, `~@x`, `#(...)`, `#inst`, `#regex`, `#php`, custom `#tag` (`Lang/TagHandlers/`). Quasiquote rewrite + auto-gensym in `Domain/Reader/QuasiquoteTransformer.php`.

**Analyzer**: Phel data to AST in `Domain/Analyzer/Ast/`. Dispatch by value type in `Domain/Analyzer/TypeAnalyzer/`:

- `AnalyzeLiteral`: scalars, keywords
- `AnalyzeSymbol`: locals, then globals, then suggestion error
- `AnalyzePersistentList`: special form (see [special-forms.md](special-forms.md)) or `InvokeSymbol`
- `AnalyzePersistentVector` / `Map` / `Set`: collection literals

Side effect: top-level `def`/`defmacro`/`ns` register names in `GlobalEnvironment` so later forms resolve.

Every node carries `NodeEnvironment` with:

- **context**: `Expression`, `Statement`, `Return` (drives emitter output shape)
- **locals** + **shadowed**: `withMergedLocals`, `withShadowedLocal`
- **recur frame**: innermost `loop`/`fn`

Wrong context yields wrong PHP. Emitter trusts what analyzer wrote.

**Emitter**: one `*Emitter.php` per AST node under `Domain/Emitter/OutputEmitter/NodeEmitter/`. Unknown node throws.

- **Munge** (`Shared/Munge.php`): `my-fn?` becomes `my_fn_QMARK_`, `+` becomes `_PLUS_`. Used by emitter and structs.
- **Source maps** (`Domain/Emitter/OutputEmitter/SourceMap/`): emitted line back to Phel `SourceLocation`.
- **Modes** (`EmitMode`): `Statement` (REPL, `eval`), `File` (normal compilation), `Cache` (cached output).

```php
(\Phel::getDefinition("phel.core", "print"))("hi");
```

**Evaluator**

- `RequireEvaluator`: temp file + `require`. Production path, opcache-friendly.
- `InMemoryEvaluator`: `eval()` for tests.

Each top-level form runs lex through eval before the next is analysed, so `defmacro` is available to following forms. See `Application/CodeCompiler.php`.

## See also

- [special-forms.md](special-forms.md), [macros.md](macros.md), [architecture.md](architecture.md), [runtime.md](runtime.md), [faq.md](faq.md)
- `src/php/Compiler/CLAUDE.md`

---
description: Phel compiler internals — phases, node types, special forms. Auto-loads when working on lexer, parser, analyzer, or emitter code.
user-invocable: false
---

# Compiler Pipeline

## Phases (strictly sequential)

```
Source → Lexer → Token[] → Parser → AST (PhpArray of Nodes)
     → Analyzer → AnalyzedNode tree → Emitter → PHP code string
```

| Phase | Location | Input | Output |
|-------|----------|-------|--------|
| Lexer | `Compiler/Lexer/` | Phel source string | `Token[]` |
| Parser | `Compiler/Parser/` | `Token[]` | AST (`PhelArray` of nodes) |
| Analyzer | `Compiler/Analyzer/` | AST nodes | `AnalyzedNode` tree |
| Emitter | `Compiler/Emitter/` | `AnalyzedNode` tree | PHP code string |

## Key Patterns

### Special Forms (Analyzer)
Special forms are handled in `Compiler/Analyzer/SpecialForm/`. Each implements analysis for a specific Phel form (`def`, `fn`, `let`, `if`, `do`, `quote`, etc.).

To add a new special form:
1. Create analyzer class in `SpecialForm/`
2. Register in the special form registry
3. Create corresponding `EmitterNode` if needed
4. Add emitter handling

### Node Types (Analyzer → Emitter)
Analyzed nodes live in `Compiler/Analyzer/Ast/`. Each node type has a corresponding emitter in `Compiler/Emitter/`.

### Environment & Scope
`NodeEnvironment` tracks:
- Local bindings and their shadowing
- Context (expression, statement, return)
- Whether result is used (for dead code elimination)

## Testing Compiler Code

- Unit tests: `tests/php/Unit/Compiler/` — test each phase in isolation
- Integration tests: `tests/php/Integration/` — end-to-end compilation
- Test method: `test_it_<describes_behavior>()`
- Use `CompilerTestHelper` for quick compile-and-eval in integration tests

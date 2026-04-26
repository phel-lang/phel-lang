---
name: compiler-guide
description: Phel compiler internals reference. Use when Codex works on lexer, parser, analyzer, emitter, compiler AST nodes, special forms, compiler tests, or compiler integration fixtures.
---

# Compiler Pipeline

## Phases

```text
Source -> Lexer -> Token[] -> Parser -> AST (PhelArray of Nodes)
       -> Analyzer -> AnalyzedNode tree -> Emitter -> PHP code string
```

| Phase | Location | Input | Output |
|-------|----------|-------|--------|
| Lexer | `Compiler/Lexer/` | Phel source string | `Token[]` |
| Parser | `Compiler/Parser/` | `Token[]` | AST (`PhelArray` of nodes) |
| Analyzer | `Compiler/Analyzer/` | AST nodes | `AnalyzedNode` tree |
| Emitter | `Compiler/Emitter/` | `AnalyzedNode` tree | PHP code string |

## Special Forms

Special forms are handled in `Compiler/Analyzer/SpecialForm/`. To add a new form:

1. Create the analyzer class in `SpecialForm/`.
2. Register it in the special form registry.
3. Create a corresponding emitter node when needed.
4. Add emitter handling.
5. Cover behavior with unit tests and an integration fixture when the emitted PHP changes.

## Node Types

Analyzed nodes live in `Compiler/Analyzer/Ast/`. Each emitted node type should have corresponding handling in `Compiler/Emitter/`.

## Environment And Scope

`NodeEnvironment` tracks local bindings, shadowing, expression or statement context, return context, and whether a result is used.

## Testing

- Unit tests live in `tests/php/Unit/Compiler/`.
- Integration tests live in `tests/php/Integration/`.
- Test method names use `test_it_<describes_behavior>()`.
- Use `CompilerTestHelper` for quick compile-and-evaluate integration scenarios.

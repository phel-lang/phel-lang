---
description: Compiler-specific conventions for lexer, parser, analyzer, and emitter code
globs: src/php/Compiler/**,tests/php/Unit/Compiler/**,tests/php/Integration/**
---

# Compiler Conventions

## Phase Ordering

Never bypass a phase. Each phase consumes only the output of the previous.

| Phase | Input | Output |
|-------|-------|--------|
| Lexer | Phel source string | `Token[]` |
| Parser | `Token[]` | AST (`PhelArray`) |
| Analyzer | AST nodes | `AnalyzedNode` tree |
| Emitter | `AnalyzedNode` tree | PHP code string |

## Key Constraints

- Analyzer nodes (`Ast/`) must carry `NodeEnvironment` with correct context
- Emitter must handle every node type — missing cases should throw, not silently skip
- Special forms are registered centrally — don't add ad-hoc handling in the analyzer loop
- Source locations must propagate through all phases for error reporting

---
description: Compiler-specific conventions for lexer, parser, analyzer, and emitter code
globs: src/php/Compiler/**,tests/php/Unit/Compiler/**,tests/php/Integration/**
---

# Compiler Conventions

## Phase Ordering

Lexer → Parser → Analyzer → Emitter. Never bypass a phase. Each phase consumes only the output of the previous phase.

## Key Constraints

- Analyzer nodes (`Ast/`) must carry `NodeEnvironment` with correct context
- Emitter must handle every node type — missing cases should throw, not silently skip
- Special forms are registered centrally — don't add ad-hoc handling in the analyzer loop
- Source locations must propagate through all phases for error reporting

## Testing

- Unit test each phase independently with minimal input
- Integration tests compile full Phel expressions and assert PHP output or eval result
- Name tests: `test_it_<phase>_<what_it_does>()` (e.g., `test_it_analyzes_let_binding`)

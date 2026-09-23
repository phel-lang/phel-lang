---
description: Compiler-specific conventions for lexer, parser, reader, analyzer, and emitter code
globs: src/php/Compiler/**,tests/php/Unit/Compiler/**,tests/php/Integration/**
---

# Compiler Conventions

Read `src/php/Compiler/CLAUDE.md` first. It holds the pipeline, the public API and the non-obvious constraints.

Lexer (`TokenStream`) → Parser (`FileNode` parse tree) → Reader (Phel data) → Analyzer (`AbstractNode` AST) → Simplifier → Emitter (PHP code). Each phase consumes only the previous phase's output. Never skip one.

- Every AST node carries a `NodeEnvironment` with the right context (expression, statement, return).
- The emitter handles every node type. A missing case throws, never skips silently.
- Source locations propagate through every phase; wrong line numbers in an error mean one dropped them.

## Adding a special form

1. Analyzer in `Domain/Analyzer/TypeAnalyzer/SpecialForm/`, dispatched from `AnalyzePersistentList`. No ad-hoc handling elsewhere.
2. A node in `Domain/Analyzer/Ast/` and its emitter in `Domain/Emitter/OutputEmitter/NodeEmitter/`.
3. A row in `docs/spec/language-surface.md`. `LanguageSurfaceSpecTest` checks the table against the dispatch.
4. A `.test` fixture per behavior, per `.agnostic-ai/rules/integration-tests.md`.

Tests: `tests/php/Unit/Compiler/` per phase, `tests/php/Integration/` end to end.

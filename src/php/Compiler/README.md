# Compiler Module

## Purpose
Core compilation pipeline for transforming Phel code into executable PHP.

## Responsibilities
- Lexical analysis (tokenizing Phel source code)
- Parsing tokens into abstract syntax tree (AST)
- Reading and transforming parse trees into Phel data structures
- Analyzing AST and performing semantic analysis
- Emitting PHP code from analyzed AST
- Evaluating Phel code at runtime
- Namespace encoding and munging
- Parentheses balancing validation

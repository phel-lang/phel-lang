# Compiler Module

## Motivation

Describe the responsibilities of the Compiler module.

## Decision

The Compiler is split into several sub-modules:

- **Lexer** – splits a string into tokens where each token has a type and position.
- **Parser** – transforms the tokens from the Lexer into a parse tree that also contains whitespace tokens.
- **Reader** – converts the parse tree into a Phel data structure, removing unnecessary whitespace.
- **Analyzer** – validates the reader result, enriches it with environment information and returns an abstract syntax tree.
- **Emitter** – turns the abstract syntax tree into PHP code.
- **Evaluator** – executes the generated PHP code string.

## Consequences

Breaking the compiler into clearly defined parts makes it easier to reason about and test the compilation process.

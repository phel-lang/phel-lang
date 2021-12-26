# Compiler Module

## Motivation

Describe the responsibilities of the Compiler module.

## Compiler sub-modules

- Lexer
- Parser
- Reader
- Analyzer
- Emitter
- Evaluator

#### Lexer

The Lexer splits a string into tokens. Each token has a type and a position.

#### Parser

The Parser transform the tokens of the Lexer into a parse tree. The parse tree contains all tokens including all whitespace tokens.

#### Reader

The reader transforms the parse tree into a Phel data structure. All unnecessary white-space tokens are removed in this step.

#### Analyzer

The analyzer analyzes the result of the reader, validates the input, and adds information to an environment. If everything is ok an abstract syntax tree is returned.

#### Emitter

The emitter takes the abstract syntax tree from the Analyzer and transforms it into a PHP code string.

#### Evaluator

The evaluator takes the generated PHP string and evaluates it.

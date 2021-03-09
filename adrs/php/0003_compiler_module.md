# Compiler Module

## Motivation

Describe the responsibilities of the compiler module

## Compiler Submodules

- Lexer: It will iterate character by character to do two things: decide where each token starts/stops and what type of token it is.
- Parser: It makes sure the code follows the correct syntax. It does this by looking at the tokens, one at a time, and deciding if the ordering is legal as defined by our language.
- Emitter: It will produce the compiled code.

- Analyzer: Analyse an AST
- Reader: Gets the AST from a given Node

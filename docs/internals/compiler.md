# Compiler Internals

This document describes how the Phel compiler processes source code. The compiler is organized as a pipeline of several steps. Each step transforms the input and passes the result to the next step.

## Compiler pipeline

1. **Lexer** – converts the source string into a stream of tokens.
2. **Parser** – turns tokens into a parse tree that still includes whitespace and comments.
3. **Reader** – builds Phel data structures from the parse tree. Trivia such as whitespace is removed.
4. **Analyzer** – validates the data structures and produces an abstract syntax tree (AST).
5. **Emitter** – transforms the AST into PHP code.
6. **Evaluator** – executes the generated PHP code.

The examples below use the expression `(print "hi")` to illustrate each stage of the pipeline.

The following sections describe each stage in more detail.

## Lexer

The lexer splits the input string into tokens. Every token has a `type`, the lexeme `code` and start/end `SourceLocation` information. Tokens are collected inside a `TokenStream`, which is consumed by the parser.

Example output:

```text
T_OPEN_PARENTHESIS "("
T_ATOM "print"
T_WHITESPACE " "
T_STRING "\"hi\""
T_CLOSE_PARENTHESIS ")"
```

## Parser

The parser reads from the `TokenStream` and produces a parse tree. The tree contains every token, including whitespace and comments, so macros can work with the original layout when required.

Example output:

```text
ListNode
  AtomNode("print")
  StringNode("hi")
```

## Reader

The reader converts the parse tree into Phel data structures. Unnecessary trivia tokens are removed at this stage. The result is wrapped into a `ReaderResult` object that keeps a reference to the original snippet for error reporting.

Example output:

```lisp
(print "hi")
```

## Analyzer

During analysis the forms produced by the reader are validated and transformed into an AST. The analyzer enriches a global environment with information such as variables or macro definitions. When successful, an `AbstractNode` tree is returned.

Example output:

```text
CallNode
  Symbol("print")
  Literal("hi")
```

## Emitter

The emitter walks the AST and converts it to PHP source code. To produce valid PHP identifiers the emitter uses the *munge* component. Munge replaces special characters in Phel symbols with safe alternatives (for example `-` becomes `_` or `+` becomes `_PLUS_`). Depending on the options, the emitter can also generate source maps.

Example output:

```php
print("hi");
```

## Evaluator

Finally the evaluator executes the emitted PHP code. The `RequireEvaluator` implementation writes the code to a temporary file and includes it so that macros and top level forms have side effects during compilation.

Example output:

```
hi
```

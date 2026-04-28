# Compiler Internals

How the Phel compiler processes source code. The compiler is a pipeline; each step transforms its input and passes the result to the next.

## Compiler pipeline

1. **Lexer**: source string into a stream of tokens.
2. **Parser**: tokens into a parse tree retaining whitespace and comments.
3. **Reader**: parse tree into Phel data structures. Trivia like whitespace is dropped.
4. **Analyzer**: validates the data structures and produces an abstract syntax tree (AST).
5. **Emitter**: AST into PHP code.
6. **Evaluator**: executes the generated PHP code.

Examples below use `(print "hi")` to illustrate each stage.

## Lexer

The lexer splits the input string into tokens. Every token has a `type`, the lexeme `code`, and start/end `SourceLocation`. Tokens are collected in a `TokenStream` consumed by the parser.

Example output:

```text
T_OPEN_PARENTHESIS "("
T_ATOM "print"
T_WHITESPACE " "
T_STRING "\"hi\""
T_CLOSE_PARENTHESIS ")"
```

## Parser

The parser reads from the `TokenStream` and produces a parse tree. The tree retains every token, including whitespace and comments, so macros can access the original layout.

Example output:

```text
ListNode
  AtomNode("print")
  StringNode("hi")
```

## Reader

The reader converts the parse tree into Phel data structures, dropping trivia tokens. The result is wrapped in a `ReaderResult` that keeps a reference to the original snippet for error reporting.

Example output:

```lisp
(print "hi")
```

## Analyzer

The analyzer validates the reader's forms and transforms them into an AST. It enriches a global environment with variables and macro definitions. On success, returns an `AbstractNode` tree.

Example output:

```text
CallNode
  Symbol("print")
  Literal("hi")
```

## Emitter

The emitter walks the AST and produces PHP source code. To produce valid PHP identifiers it uses the *munge* component, which replaces special characters in Phel symbols (e.g. `-` becomes `_`, `+` becomes `_PLUS_`). The emitter can also generate source maps.

Example output:

```php
print("hi");
```

## Evaluator

The evaluator executes the emitted PHP. `RequireEvaluator` writes the code to a temp file and includes it so macros and top-level forms have side effects during compilation.

Example output:

```
hi
```

+++
title = "REPL"
weight = 18
+++

Phel provides a interactive prompt where you can type Phel expressions and see the results. This interactive prompt is called REPL (stands for Read-eval-print loop). A REPL is very helpful to test out small task or to play around with the language its self.

The REPL can be started with the following command:
```bash
./vendor/bin/phel repl
```

Afterwards you can type an expression and see the result intermediately

```bash
Welcome to the Phel Repl
Type "exit" or press Ctrl-D to exit.
>>> (* 6 7)
42
>>>
```

Press `Ctrl-D` or type "exit" to end the REPL session.

## Little helpers

The REPL itsself provides a few little helpers to make you life easier.

The `doc` function returns the documentation for any definition in your scope:
```bash
>>> (doc all?)
(all? pred xs)

Returns true if `(pred x)` is logical true for every `x` in `xs`, else false.
nil
>>>
```

The `require` function can be used to require another namespace into the repl. The arguments are the same as the `:require` statement in the `ns` function.
```bash
>>> (require phel\html :as h)
phel\html
>>> (h/html [:span])
<span></span>
>>>
```

The `use` function can be used to add a alias for a PHP class. The arguments are the same as the `:use` statement in the `ns` function.
```bash
>>> (use \Phel\Lang\Symbol :as PhelSymbol)
\Phel\Lang\Symbol
>>> (php/:: PhelSymbol (create "foo"))
foo
>>>
```

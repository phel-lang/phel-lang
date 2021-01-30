+++
title = "REPL"
weight = 18
+++

Phel comes with an interactive prompt. The prompt accepts Phel expressions and directly returns the result. This interactive prompt is called REPL (stands for Read-eval-print loop). A REPL is very helpful to test out small tasks or to play around with the language itself.

The REPL is started with the following command:
```bash
./vendor/bin/phel repl
```

Afterwards any Phel expression can be typed in.

```bash
Welcome to the Phel Repl
Type "exit" or press Ctrl-D to exit.
phel:1> (* 6 7)
42
phel:2>
```

The prompt also accepts multiline expressions:
```bash
Welcome to the Phel Repl
Type "exit" or press Ctrl-D to exit.
phel:1> (+
....:2>  3
....:3>  7)
10
phel:4>
```

Press `Ctrl-D` or type "exit" to end the REPL session.

## Little helpers

The REPL itself provides a few little helper functions.

The `doc` function returns the documentation for any definition in the current scope:
```bash
phel:1> (doc all?)
(all? pred xs)

Returns true if `(pred x)` is logical true for every `x` in `xs`, else false.
nil
phel:2>
```

The `require` function can be used to require another namespace into the REPL. The arguments are the same as the `:require` statement in the `ns` function.
```bash
phel:1> (require phel\html :as h)
phel\html
phel:2> (h/html [:span])
<span></span>
phel:3>
```

The `use` function can be used to add a alias for a PHP class. The arguments are the same as the `:use` statement in the `ns` function.
```bash
phel:1> (use \Phel\Lang\Symbol :as PhelSymbol)
\Phel\Lang\Symbol
phel:2> (php/:: PhelSymbol (create "foo"))
foo
phel:3>
```

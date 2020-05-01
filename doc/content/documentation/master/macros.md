+++
title = "Macros (TODO)"
weight = 100
+++

## Quote

```phel
(quote form)
```
Yields the unevaluated _form_. Preceding a form with a single quote is a shorthand for `(quote form)`.

```phel
(quote 1) # Evaluates to 1
(quote hi) # Evaluates the symbol hi
(quote quote) # Evaluates to the symbol quote

'(1 2 3) # Evaluates to the tuple (1 2 3)
'(print 1 2 3) # Evaluates to the tuple (print 1 2 3). Nothing is printed.
```

## Quasiquote
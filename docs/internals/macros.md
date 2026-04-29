# Macros

A macro is a function that runs at compile time, takes Phel forms as input, and returns Phel forms. By the time the analyzer sees a macro call, the call has already been replaced by whatever the macro returned. The emitter never sees the macro itself — only its expansion.

This page covers how the compiler finds and runs macros, how `macroexpand` works, how quasiquote rewriting and gensym keep hygiene under control, and the few ways this can bite.

## Compile-time vs. runtime, again

`(defmacro when [test & body] ...)` is a `def` whose `Variable` is tagged with `:macro true`. At runtime it is just a function. What makes it a macro is the analyzer:

1. Analyzer sees a list `(when x y)`.
2. It resolves the head `when` to a `GlobalVarNode`.
3. The node says `isMacro() === true`.
4. The analyzer calls the function **right now**, passing the unevaluated form, an env map, and the rest of the args.
5. Whatever the macro returns gets analysed in place of the original list.

Source: `Compiler/Application/Analyzer.php` (the macro-call branch) and `Compiler/Application/MacroExpander.php`. Top-level forms are compiled and evaluated one at a time so a `defmacro` is available to subsequent forms in the same file.

## `macroexpand-1` vs `macroexpand`

The Phel facade exposes both via `CompilerFacade`:

- `macroexpand1($form)` — expand once. If the head isn't a macro, returns the form unchanged.
- `macroexpand($form)`  — expand repeatedly until the result is no longer a macro call (fixed point).

This is what the REPL's `(macroexpand-1 'form)` and the nREPL `macroexpand` op call into. Use them constantly when authoring macros.

```phel
(macroexpand-1 '(when x y z))
;; => (if x (do y z) nil)

(macroexpand '(or a b c))
;; => (let [or__1 a] (if or__1 or__1 (let [or__2 b] (if or__2 or__2 c))))
```

The expander only walks the *outermost* form — it does not recurse into subforms. That is the analyzer's job.

## How a macro receives its arguments

A macro call `(my-macro a b c)` invokes the macro function as `myMacro($form, $env, a, b, c)`:

- `$form` — the *whole* call list, including the head symbol. Useful for source location and error messages.
- `$env` — a Phel map of currently-known locals (empty for top-level / `macroexpand-1` calls outside an analyzer context).
- The remaining positional args are the unevaluated argument forms.

This matches the `&form` / `&env` convention exposed in user-facing macros.

## Quasiquote rewriting

Almost every non-trivial macro is built around `` `(...) ``. The reader rewrites quasiquote into explicit list construction *before* the analyzer ever sees the form. The transform lives in `Compiler/Domain/Reader/QuasiquoteTransformer.php`.

Roughly:

| Reader input | Quasiquote output |
|--------------|-------------------|
| `` `x `` | `(quote x)` |
| `` `~x `` | `x` |
| `` `(a b) `` | `(list (quote a) (quote b))` |
| `` `(a ~x) `` | `(list (quote a) x)` |
| `` `(a ~@xs b) `` | `(concat (list (quote a)) xs (list (quote b)))` |
| `` `[a ~x] `` | `(vec (concat (list (quote a)) (list x)))` |

Symbols inside a quasiquote are namespace-qualified to the *defining* namespace. That is the easy half of macro hygiene: a macro that writes `` `(map f xs) `` always refers to `phel\core/map`, even if the caller has shadowed `map` with their own local.

## Auto-gensym (`x#`)

The hard half of hygiene is binding names. Inside a quasiquote, any symbol ending with `#` becomes a fresh gensym, consistent within that quasiquote.

```phel
`(let [x# 1] (+ x# x#))
;; expands to something like:
(let [x__123__auto__ 1] (+ x__123__auto__ x__123__auto__))
```

Mechanism: `Compiler/Domain/Reader/GensymContext.php` keeps a per-quasiquote map from `"x#"` to a freshly generated `Symbol`. First sighting calls `Symbol::gen("x")`; subsequent sightings reuse it. A new gensym context is created for each top-level quasiquote.

Without auto-gensym, a macro like `(when x ...)` that introduced its own `x` binding would shadow a caller's `x`. With auto-gensym, the introduced name is unique by construction.

`gensym` is also available as an explicit function: `(gensym)` or `(gensym "prefix")`.

## When macros bite

- **Order matters at the top level.** A `defmacro` only affects forms that come *after* it in the same file or any file `require`d later. Tests that run `(defmacro foo ...)` and `(foo ...)` in the same form will fail; split them.
- **Macros must return Phel data, not PHP values.** `(rand)` inside a macro body runs at compile time and bakes a random number into the source. That is almost never what you want.
- **Side effects at compile time are real.** `println` inside a macro fires during compilation. Useful for debugging; surprising in production builds.
- **Equality comparison ends recursion.** `macroexpand` stops when consecutive expansions are `equals()`. A macro that returns a structurally identical form (e.g. wraps in a no-op) terminates fine; one that produces a non-converging chain is your bug.

## Debugging macros

| Tool | What it shows |
|------|---------------|
| `(macroexpand-1 'form)` in the REPL | First expansion only |
| `(macroexpand 'form)` in the REPL | Full fixed point |
| nREPL `macroexpand` op | Same, exposed to editors |
| `Compiler/Application/MacroExpander.php` | The exact PHP that resolves and invokes the macro |
| Integration fixtures `tests/php/Integration/Fixtures/<form>/` | Round-trip checks for built-in macros that compile to special forms |

## See also

- [special-forms.md](special-forms.md) — what macros ultimately expand into
- [compiler.md](compiler.md) — how each top-level form is compiled and evaluated before the next
- `src/php/Compiler/Domain/Reader/QuasiquoteTransformer.php` — the quasiquote rewrite, line by line

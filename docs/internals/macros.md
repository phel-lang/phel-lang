# Macros

Compile-time function. Takes Phel forms, returns Phel forms. Analyzer replaces the call with the result. Emitter never sees the macro.

## Mechanism

`(defmacro when [test & body] ...)` is a `def` whose `Variable` carries `:macro true`. At runtime: a function. At compile time, the analyzer:

1. Sees `(when x y)`.
2. Resolves head to `GlobalVarNode`.
3. Checks `isMacro() === true`.
4. Calls the function now: form, env, then args.
5. Analyses the returned form in place.

Source: `Compiler/Application/Analyzer.php` (macro branch), `Compiler/Application/MacroExpander.php`. Top-level forms compile + eval one at a time, so `defmacro` is available to subsequent forms in the same file.

## `macroexpand-1` vs `macroexpand`

`CompilerFacade`:

- `macroexpand1($form)`: expand once. Not a macro head: unchanged.
- `macroexpand($form)`: fixed point.

REPL `(macroexpand-1 'form)` and nREPL `macroexpand` op route here.

```phel
(macroexpand-1 '(when x y z))
;; => (if x (do y z) nil)

(macroexpand '(or a b c))
;; => (let [or__1 a] (if or__1 or__1 (let [or__2 b] (if or__2 or__2 c))))
```

Outermost only. No recursion into subforms (analyzer's job).

## Macro arguments

`(my-macro a b c)` invokes `myMacro($form, $env, a, b, c)`:

- `$form`: full call list including head. Useful for source location.
- `$env`: Phel map of locals (empty for top-level / `macroexpand-1`).
- Remaining args: unevaluated argument forms.

Matches user-facing `&form` / `&env`.

## Quasiquote

`Compiler/Domain/Reader/QuasiquoteTransformer.php` rewrites `` `(...) `` before the analyzer:

| Input | Output |
|-------|--------|
| `` `x `` | `(quote x)` |
| `` `~x `` | `x` |
| `` `(a b) `` | `(list (quote a) (quote b))` |
| `` `(a ~x) `` | `(list (quote a) x)` |
| `` `(a ~@xs b) `` | `(concat (list (quote a)) xs (list (quote b)))` |
| `` `[a ~x] `` | `(vec (concat (list (quote a)) (list x)))` |

Symbols inside quasiquote get namespace-qualified to the *defining* namespace. Easy half of hygiene: `` `(map f xs) `` resolves to `phel\core/map` regardless of caller shadowing.

## Auto-gensym (`x#`)

Hard half of hygiene: binding names. Inside a quasiquote, trailing `#` produces a fresh gensym consistent within that quasiquote.

```phel
`(let [x# 1] (+ x# x#))
;; ≈ (let [x__123__auto__ 1] (+ x__123__auto__ x__123__auto__))
```

`Compiler/Domain/Reader/GensymContext.php`: per-quasiquote map from `"x#"` to a generated `Symbol`. First sight: `Symbol::gen("x")`. Subsequent: reuse. New context per top-level quasiquote.

Explicit form: `(gensym)` or `(gensym "prefix")`.

## Common bites

- **Order matters.** `defmacro` only affects later forms. `(defmacro foo ...)` and `(foo ...)` in the same compilation unit will not see each other; split.
- **Return Phel data, not PHP values.** `(rand)` in a macro body bakes one number into source.
- **Compile-time side effects fire during compile.** `println` in a macro runs at build, not runtime.
- **`macroexpand` ends on `equals()`.** A macro that produces a structurally identical form terminates fine; non-converging chain is a bug.

## Debugging

| Tool | Shows |
|------|-------|
| `(macroexpand-1 'form)` | One expansion |
| `(macroexpand 'form)` | Fixed point |
| nREPL `macroexpand` op | Same, for editors |
| `Compiler/Application/MacroExpander.php` | Resolution + invocation path |
| `tests/php/Integration/Fixtures/<form>/` | Round-trip checks |

## See also

[special-forms.md](special-forms.md), [compiler.md](compiler.md), `Compiler/Domain/Reader/QuasiquoteTransformer.php`.

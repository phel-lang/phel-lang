# Phel Internals

How Phel is built. Read these in order if you want a full mental model; jump straight to a topic if you only need one answer.

## Map

1. [Architecture](architecture.md) — module layout, the Gacela pattern, dependency map. Where things live and why.
2. [Compiler](compiler.md) — the six-stage pipeline (Lexer → Parser → Reader → Analyzer → Emitter → Evaluator), worked example, source paths.
3. [Special forms](special-forms.md) — full list, how dispatch works, how to add one.
4. [Macros](macros.md) — `macroexpand`, quasiquote rewriting, auto-gensym, hygiene.
5. [Runtime](runtime.md) — `Lang/`: persistent collections, `Registry`, `Variable`, the `\Phel` static facade.
6. [Benchmarks](benchmarks.md) — PHPBench setup and how to spot regressions.
7. [FAQ](faq.md) — common questions, grouped by reader (PHP dev, Clojure dev, compiler hacker, tool builder, bug hunter).

## When to read what

| You want to… | Read |
|--------------|------|
| Understand the whole system in one sitting | architecture → compiler → runtime |
| Add a special form or fix the analyzer | compiler → special-forms |
| Write or debug a macro | macros (and `(macroexpand-1 ...)` in the REPL) |
| Build an editor / linter / static tool | architecture → faq ("I'm building a tool") |
| Track down a compilation bug | compiler → faq ("I'm investigating a bug") |
| Profile the compiler or core ops | benchmarks |

## Adjacent reading

- Each module under `src/php/` ships a `CLAUDE.md` with its public API and constraints — start there before editing the module.
- `.claude/rules/compiler.md` and `.claude/rules/integration-tests.md` cover phase ordering and fixture conventions enforced by tooling.
- User-facing docs live one directory up: `docs/quickstart.md`, `docs/php-interop.md`, `docs/data-structures-guide.md`.

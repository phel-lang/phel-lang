# Phel Internals

## Pages

1. [Architecture](architecture.md): module layout, Gacela pattern, dependency map.
2. [Compiler](compiler.md): seven-stage pipeline with worked example.
3. [Special forms](special-forms.md): list, dispatch, how to add one.
4. [Macros](macros.md): `macroexpand`, quasiquote, auto-gensym.
5. [Runtime](runtime.md): `Lang/`, persistent collections, `Registry`, `\Phel` facade.
6. [Benchmarks](benchmarks.md): PHPBench setup.
7. [FAQ](faq.md): grouped by reader.

## What to read for what

| Goal | Path |
|------|------|
| Whole system | architecture, compiler, runtime |
| Add a special form / fix analyzer | compiler, special-forms |
| Write or debug a macro | macros + `(macroexpand-1 ...)` |
| Build an editor / linter / tool | architecture, faq (tool builder) |
| Compilation bug | compiler, faq (bug hunting) |
| Profile | benchmarks |

## Adjacent

- Each `src/php/<Module>/CLAUDE.md`: public API + constraints. Read before editing.
- `.claude/rules/compiler.md`, `.claude/rules/integration-tests.md`: phase ordering, fixture conventions.
- User docs on the website: [getting started](https://phel-lang.org/documentation/getting-started/), [php-interop](https://phel-lang.org/documentation/php-interop/), [data structures](https://phel-lang.org/documentation/language/data-structures/).

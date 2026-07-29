# ADR 0002: Compile to PHP source, not a bytecode VM

- **Status**: Accepted (recorded retroactively; the decision predates this record)
- **Date**: 2026-07-29

## Context

A Lisp on a host platform has three ways to run: walk its own AST at runtime,
compile to a bytecode it executes itself, or lower to the host language and let
the host run it. Phel's premise is that a PHP project can adopt it file by file,
which puts hard requirements on whatever runs the code. It has to interoperate
with PHP objects at zero marshalling cost, appear in PHP stack traces, and survive
being deployed to hosting where the only artifact you can ship is `.php` files.

An AST walker fails all three at once: every Phel call becomes an interpreter
frame the host cannot see into, opcache has nothing to cache, and a profiler
reports time inside the interpreter rather than inside user code. A self-hosted VM
fails them harder and adds an implementation nobody asked for.

## Decision

Every Phel form is lowered to PHP source text, written to a file, and `require`d.
There is no runtime AST walker and no Phel bytecode.

- The emitter produces plain PHP that calls a small static surface, `\Phel::*`
  (`src/Phel.php`), for definition registration and literal construction.
- `Domain/Evaluator/RequireEvaluator` writes the emitted code to a temp file named
  by the MD5 of its content, calls `opcache_compile_file()` when available, and
  `require`s it. Identical code hits a process-local cache and then the file.
- `InMemoryEvaluator` (`eval()`) exists for tests only.
- Compilation and evaluation interleave per top-level form: each form is lexed,
  parsed, read, analysed, emitted and evaluated before the next is analysed. This
  is what makes a `defmacro` available to the forms after it in the same file, and
  what keeps `GlobalEnvironment` (compile time) and `Lang\Registry` (runtime) in
  step.

## Consequences

Phel inherits PHP's execution model wholesale, including what PHP does not have.
There is no tail-call elimination, so `recur` is a special form that compiles a
loop body to `while (true)` with rebound parameters rather than a general
optimisation. Continuations and stack manipulation are out for the same reason.

What it buys is the entire PHP toolchain, on the generated code: opcache caches
it, Xdebug steps through it, PHPStan and Psalm can read it, and a stack trace from
production points at real files. Source maps
(`Domain/Emitter/OutputEmitter/SourceMap/`) map those lines back to `.phel`, which
means the mapping is a debugging aid layered on top of a working trace, not the
only thing standing between a user and an opaque error.

The cost that shows up in practice is that emitted text is an interface people can
see, and therefore one they want to depend on. It is explicitly excluded from the
stability promise: only the behaviour of the emitted code is promised, never its
shape. The integration fixtures pin the text so that changes are reviewed, not so
that they are forbidden.

Writing to a temp file also means the compile path touches the filesystem on every
uncached form, which is why the evaluator carries two cache layers and why
`.phel/cache/` exists at all.

## Enforcement

- `tests/php/Integration/Fixtures/**/*.test` pins emitted PHP per construct with a
  `--PHEL--` / `--PHP--` pair, so an emitter change surfaces as a reviewable diff.
- `tests/php/Integration/` boots the real facade rather than a stub, so the
  compile-then-`require` path is the one under test.
- The "not frozen" section of [the stability policy](../stability.md#explicitly-not-covered)
  states that the emitted text carries no promise.

## Alternatives considered

- **Runtime AST interpreter.** Rejected: no opcache, no usable traces, and a
  permanent per-call cost on the hot path.
- **A Phel bytecode VM in PHP.** Rejected for the same reasons plus the cost of
  owning a VM, and it would sever cheap interop, which is the point of the project.
- **Emitting a PHP AST through a parser library instead of text.** Not adopted:
  text is what a user reads when debugging a surprising expansion, and the
  fixtures depend on it being readable.

## See also

- [Compiler internals](../internals/compiler.md), [Runtime](../internals/runtime.md)
- [Internals FAQ](../internals/faq.md): "Interpreter or compiler?"
- [ADR 0009](0009-stdlib-in-phel-precompiled-in-the-phar.md): the same decision at
  distribution time.

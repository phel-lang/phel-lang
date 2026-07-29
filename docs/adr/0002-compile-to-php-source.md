# ADR 0002: Compile to PHP source, not a bytecode VM

- **Status**: Accepted (recorded retroactively; predates this record)
- **Date**: 2026-07-29

## Context

A hosted Lisp can walk its own AST, run its own bytecode, or lower to the host.
Phel's premise is adoption file by file inside a PHP project, which requires zero
marshalling to PHP objects, visibility in PHP stack traces, and deployability
where the only shippable artifact is `.php`.

An AST walker fails all three: every call is an interpreter frame, opcache has
nothing to cache, and profilers report time in the interpreter. A self-hosted VM
fails them harder and adds an implementation nobody asked for.

## Decision

Every form is lowered to PHP source, written to a file, and `require`d. No runtime
AST walker, no Phel bytecode.

- Emitted PHP calls a small static surface, `\Phel::*` (`src/Phel.php`), for
  definition registration and literal construction.
- `Domain/Evaluator/RequireEvaluator` writes the code to a temp file named by its
  MD5, calls `opcache_compile_file()` when available, and `require`s it. Repeat
  code hits a process-local cache, then the file.
- `InMemoryEvaluator` (`eval()`) is for tests only.
- Compilation and evaluation interleave per top-level form. That is why a
  `defmacro` is available to later forms in the same file, and why
  `GlobalEnvironment` (compile time) and `Lang\Registry` (runtime) stay in step.

## Consequences

Phel inherits PHP's execution model, including its gaps. No tail-call elimination,
so `recur` is a special form compiling to `while (true)` with rebound parameters
rather than a general optimisation. No continuations.

In exchange, the whole PHP toolchain works on the generated code: opcache, Xdebug,
PHPStan, Psalm, and real file paths in production traces. Source maps
(`Domain/Emitter/OutputEmitter/SourceMap/`) map back to `.phel` on top of a trace
that already works.

Emitted text is visible, so people depend on it. Only its behaviour is promised.
Fixtures pin the text so changes are reviewed, not forbidden.

The compile path touches the filesystem for every uncached form. Hence the two
cache layers in the evaluator and `.phel/cache/`.

## Enforcement

- `tests/php/Integration/Fixtures/**/*.test`: `--PHEL--` / `--PHP--` pairs pin
  emitted PHP per construct
- `tests/php/Integration/` boots the real facade, so compile-then-`require` is the
  path under test
- [Stability policy](../stability.md#explicitly-not-covered): emitted text carries
  no promise

## Alternatives considered

- **Runtime AST interpreter.** No opcache, no usable traces, permanent per-call
  cost.
- **A Phel bytecode VM in PHP.** Same, plus owning a VM, plus losing cheap interop.
- **Emitting a PHP AST via a parser library.** Text is what a user reads when
  debugging an expansion, and the fixtures depend on it.

## See also

- [Compiler](../internals/compiler.md), [Runtime](../internals/runtime.md),
  [FAQ](../internals/faq.md)
- [ADR 0009](0009-stdlib-in-phel-precompiled-in-the-phar.md): same decision at
  distribution time

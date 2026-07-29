# ADR 0002: Compile to PHP source, not a bytecode VM

- **Status**: Accepted (recorded retroactively)
- **Date**: 2026-07-29

## Context

A hosted Lisp can walk its own AST, run its own bytecode, or lower to the host.
Phel's premise is adoption file by file inside a PHP project, which needs zero
marshalling to PHP objects, visibility in PHP stack traces, and deployability where
the only shippable artifact is `.php`.

An AST walker fails all three: every call is an interpreter frame, opcache has
nothing to cache, profilers report time in the interpreter. A VM fails harder.

## Decision

Every form is lowered to PHP source, written to a file, and `require`d. No runtime
AST walker, no Phel bytecode.

- Emitted PHP calls `\Phel::*` (`src/Phel.php`) for definition registration and
  literal construction.
- `Domain/Evaluator/RequireEvaluator` writes to a temp file named by the code's
  MD5, calls `opcache_compile_file()` when available, `require`s it. Repeat code
  hits a process-local cache, then the file.
- `InMemoryEvaluator` (`eval()`) is for tests only.
- Compilation and evaluation interleave per top-level form. Hence `defmacro` being
  available to later forms in the same file, and `GlobalEnvironment` (compile time)
  staying in step with `Lang\Registry` (runtime).

## Consequences

Phel inherits PHP's execution model including its gaps. No tail-call elimination,
so `recur` compiles to `while (true)` with rebound parameters rather than being a
general optimisation. No continuations.

In exchange the whole PHP toolchain works on generated code: opcache, Xdebug,
PHPStan, Psalm, real paths in production traces. Source maps
(`Domain/Emitter/OutputEmitter/SourceMap/`) map back to `.phel` on top of a trace
that already works.

Emitted text is visible, so people depend on it. Only its behaviour is promised;
fixtures pin the text so changes are reviewed, not forbidden.

Every uncached form touches the filesystem. Hence two cache layers and
`.phel/cache/`.

## Enforcement

- `tests/php/Integration/Fixtures/**/*.test`: `--PHEL--` / `--PHP--` pairs pin
  emitted PHP per construct
- `tests/php/Integration/` boots the real facade, so compile-then-`require` is the
  path under test
- [Stability policy](../stability.md#explicitly-not-covered): emitted text carries
  no promise

## Alternatives considered

- **Runtime AST interpreter.** No opcache, no usable traces, permanent per-call cost.
- **A Phel bytecode VM.** Same, plus owning a VM, plus losing cheap interop.
- **Emitting a PHP AST via a library.** Text is what a user reads when debugging an
  expansion, and the fixtures depend on it.

## See also

[Compiler](../internals/compiler.md) · [Runtime](../internals/runtime.md) ·
[FAQ](../internals/faq.md) ·
[ADR 0009](0009-stdlib-in-phel-precompiled-in-the-phar.md)

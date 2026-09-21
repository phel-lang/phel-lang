# ADR 0019: `withMeta` returns a copy

- **Status**: Accepted
- **Date**: 2026-09-21

## Context

`MetaInterface::withMeta()` had two contracts. `MetaTrait` assigned metadata to the receiver and returned it, while persistent collections and numeric values returned a new instance. A caller holding the interface could not know whether discarding the result lost the update or whether retaining another reference exposed a mutation. The split also prevented `#[NoDiscard]` from enforcing correct use at the interface boundary. Issue #3311 records the decision before the PHP API stability promise starts at 1.0.

## Decision

Every `MetaInterface::withMeta()` implementation returns a value carrying the supplied metadata and leaves the receiver unchanged. The interface and every concrete implementation carry `#[NoDiscard]`. `MetaTrait` performs a shallow clone before replacing metadata.

Analyzer type inference replaces binding, shadow and parameter Symbols with tagged copies. Late inference results are also published through the shared `NodeEnvironment` fact table so local-reference nodes analyzed before the copy can read them without mutating a shared Symbol.

`PhelVar::withMeta()` returns a handle-local copy. Its `meta()`, `alterMeta()` and `resetMeta()` methods continue to address canonical metadata shared by every handle to the registry slot.

Atoms remain mutable references, but metadata mutation is explicit through `Atom::alterMeta()` and `Atom::resetMeta()`. The Phel `alter-meta!` and `reset-meta!` functions use those operations.

## Consequences

`$value->withMeta($meta);` is always a lost result and warns. Callers must use the returned value. Another reference to the receiver never observes metadata added through `withMeta()`.

The clone is shallow. Mutable state owned by an `Atom` or function object is shared according to PHP clone semantics; this decision changes metadata attachment, not the value's own mutation contract.

Analyzer inference carries a small object-keyed side table. `WeakMap` keeps those facts scoped to live Symbols and avoids turning inference into hidden Symbol mutation.

## Enforcement

`NoDiscardTest` pins copy identity, receiver immutability and the discarded-result warning for `MetaTrait`. Existing per-value metadata tests cover the explicit implementations. `BindingTypeInferrerTest` pins replacement of both binding Symbols and late loop-tag visibility. Compiler integration fixtures pin inferred parameter signatures and loop specialization. `ProfilerSessionTest` pins metadata on wrapped functions.

## Alternatives considered

- **Document the split contract.** It leaves the interface unable to state whether the receiver changes and cannot support `#[NoDiscard]` consistently.
- **Split readable and mutable metadata interfaces.** It adds public surface while leaving value callers to choose between two operations.
- **Keep analyzer mutation as an internal exception.** Shared AST Symbols would still change through an alias, preserving the bug class this decision removes.

## See also

[#3311](https://github.com/phel-lang/phel-lang/issues/3311) · [ADR 0011](0011-persistent-collections-in-php.md) · `src/php/Lang/CLAUDE.md` · `src/php/Compiler/CLAUDE.md`

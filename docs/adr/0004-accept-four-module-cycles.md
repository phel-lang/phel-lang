# ADR 0004: Accept four module cycles and pin them

- **Status**: Accepted (recorded retroactively; decided in #2785 and earlier)
- **Date**: 2026-07-29

## Context

[ADR 0003](0003-modules-talk-through-facades.md) makes the facade the only door
between modules. The natural next rule is "and the module graph is acyclic",
which is what a dependency test is usually written to enforce. Phel's graph is
not acyclic, and four pairs point at each other:

| Pair | Why it closes |
|---|---|
| `Compiler <-> Shared` | `Shared\Facade\CompilerFacadeInterface` names 11 compiler types: six in method signatures (`AbstractNode`, `NodeEnvironmentInterface`, `GlobalEnvironmentInterface`, `TokenStream`, `EmitterResult`, `ReaderResult`) and five in `@throws` tags |
| `Lang <-> Shared` | `AbstractType::__toString()` needs `Printer`; `AbstractPersistentStruct` needs `Munge` |
| `Api <-> Run` | The only mutual Gacela provider pair: each genuinely uses the other's facade |
| `Phel <-> Run` | The composition root wires `RunFacade`, and `RunCommand` calls back into `Phel::setupRuntimeArgs()` |

The `Compiler <-> Shared` case is the one that was argued at length, in
[#2785](https://github.com/phel-lang/phel-lang/pull/2785). A strongly connected
component decomposes only when *every* back-edge goes, so partial cleanups are
pure churn: moving the five exception classes out leaves the cycle exactly where
it was. Removing all eleven means moving the analyzer AST into `Shared`, and
`AbstractNode` alone has roughly 554 references outside `Shared`. `Shared` would
become the compiler, inverting the leaf-layer rule it exists to enforce.

The two alternatives weighed there cost more than the cycle. Passing primitives or
serialized handles across the boundary turns `analyze()` into an untyped array and
discards the typing that PHPStan level 9 and Psalm level 1 enforce over those
references. Narrow `Shared` interfaces for the AST fail because analyzer consumers
match on concrete node types, so the interface either restates the node hierarchy
or is too generic to type anything. The parse tree is the contrast that proves the
shape matters: `Shared\Parser\Node\NodeInterface` works precisely because a parse
tree has a genuinely narrow contract.

## Decision

Exactly four cyclic module pairs exist. They are named, each documented in the
owning module's `CLAUDE.md`, and pinned by a test that also pins the individual
files closing each direction. A fifth cycle needs a written rationale, not just a
green build.

The cycles are a bounded exception to ADR 0003, not a softening of it: every one
of them is still facade-typed or leaf-utility traffic, and no module reaches into
another's `Domain/` because of them.

## Consequences

The dependency test cannot be read as "no cycles", so it carries its own rationale
inline and asserts two things: the set of pairs, and the specific files that close
three of the four. Three of the cycles are one file wide on at least one side, so a
regression shows up as a *new* file in the pinned list rather than as a new pair,
which is a much earlier signal.

`Compiler <-> Shared` is excluded from the file-level pin: its Compiler to Shared
side is around 80 files by design, and its single back-edge file is pinned by
`SharedCompilerBoundaryTest` instead.

Accepting the cycles has a maintenance cost that is real but small and known:
these five modules (`Compiler`, `Config`, `Filesystem`, `Lang`, `Shared`) load
together, so none of them can be extracted into a separate package without
revisiting this. Nobody is asking to.

The cost of *not* accepting them would have been paid in type safety, which the
project spends heavily to keep.

## Enforcement

- `tests/php/Unit/Architecture/ModuleDependencyCycleTest.php`: the four pairs plus
  the files closing three of them. The graph is built from `use Phel\<Module>\…;`
  statements only, because that is the only form creating compile-time coupling.
  Docblock `{@see}` tags and class names inside generated-code templates are
  ignored on purpose: they read like edges and bind nothing.
- `tests/php/Unit/Architecture/SharedCompilerBoundaryTest.php`: fails if a second
  `Shared -> Compiler` import appears, or if the 11-symbol set changes.

## Alternatives considered

- **Break every cycle.** Rejected for `Compiler <-> Shared` on the grounds above,
  and the others are one file wide, where the fix costs more than the edge.
- **Drop the acyclicity goal entirely.** Rejected: without a pinned list, the four
  become forty, one reasonable-looking import at a time.
- **Allow cycles but only inside a documented "kernel" group.** This is close to
  what exists, minus the per-file pinning that gives the early signal.

## See also

- `src/php/Shared/CLAUDE.md`: the full `Compiler <-> Shared` argument, including
  why the cycle is benign at runtime
- `src/php/Lang/CLAUDE.md`, `src/php/Api/CLAUDE.md`, `src/php/Run/CLAUDE.md`
- [ADR 0003](0003-modules-talk-through-facades.md)

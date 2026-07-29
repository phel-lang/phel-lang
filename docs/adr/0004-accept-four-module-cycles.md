# ADR 0004: Accept four module cycles and pin them

- **Status**: Accepted (recorded retroactively; decided in #2785 and earlier)
- **Date**: 2026-07-29

## Context

[ADR 0003](0003-modules-talk-through-facades.md) makes the facade the only door
between modules. The usual next rule is an acyclic graph. Phel's is not acyclic:

| Pair | Why it closes |
|---|---|
| `Compiler <-> Shared` | `Shared\Facade\CompilerFacadeInterface` names 11 compiler types: 6 in signatures (`AbstractNode`, `NodeEnvironmentInterface`, `GlobalEnvironmentInterface`, `TokenStream`, `EmitterResult`, `ReaderResult`), 5 in `@throws` |
| `Lang <-> Shared` | `AbstractType::__toString()` needs `Printer`; `AbstractPersistentStruct` needs `Munge` |
| `Api <-> Run` | The only mutual Gacela provider pair; each uses the other's facade |
| `Phel <-> Run` | The composition root wires `RunFacade`; `RunCommand` calls `Phel::setupRuntimeArgs()` |

`Compiler <-> Shared` was argued in
[#2785](https://github.com/phel-lang/phel-lang/pull/2785). A strongly connected
component decomposes only when every back-edge goes, so partial cleanups are
churn: moving the five exceptions out leaves the cycle intact. Removing all eleven
means moving the analyzer AST into `Shared`, and `AbstractNode` alone has ~554
references outside it. `Shared` would become the compiler.

Both alternatives cost more than the cycle. Primitives or serialized handles at
the boundary turn `analyze()` into an untyped array, discarding what PHPStan
level 9 and Psalm level 1 enforce over those references. Narrow `Shared`
interfaces fail because analyzer consumers match on concrete node types, so the
interface either restates the hierarchy or types nothing.
`Shared\Parser\Node\NodeInterface` works because a parse tree has a genuinely
narrow contract.

## Decision

Exactly four cyclic pairs exist. Each is documented in the owning module's
`CLAUDE.md` and pinned by a test that also pins the files closing each direction.
A fifth needs a written rationale, not just a green build.

They are a bounded exception to ADR 0003: all traffic is still facade-typed or
leaf-utility, and no module reaches into another's `Domain/` because of them.

## Consequences

The dependency test cannot be read as "no cycles", so it carries its rationale
inline and asserts both the pairs and the files closing three of them. Those three
are one file wide on at least one side, so a regression appears as a new file in
the pinned list rather than a new pair, which is an earlier signal.

`Compiler <-> Shared` is excluded from the file-level pin: its Compiler to Shared
side is ~80 files by design, and its single back-edge file is pinned by
`SharedCompilerBoundaryTest`.

Known cost: `Compiler`, `Config`, `Filesystem`, `Lang` and `Shared` load together
and cannot be split into separate packages without revisiting this. Nobody is
asking to. The alternative would have been paid in type safety.

## Enforcement

- `ModuleDependencyCycleTest`: the four pairs plus the files closing three. The
  graph is built from `use Phel\<Module>\…;` only, since that is the only form
  creating compile-time coupling. Docblock `{@see}` tags and class names in
  generated-code templates are ignored on purpose.
- `SharedCompilerBoundaryTest`: fails on a second `Shared -> Compiler` import or a
  change to the 11-symbol set.

## Alternatives considered

- **Break every cycle.** Rejected for `Compiler <-> Shared` above; the others are
  one file wide, where the fix costs more than the edge.
- **Drop acyclicity as a goal.** Without a pinned list, four become forty.
- **Allow cycles inside a documented kernel group.** Close to what exists, minus
  the per-file pinning that gives the early signal.

## See also

- `src/php/Shared/CLAUDE.md`: the full argument, including why the cycle is benign
  at runtime
- `src/php/Lang/CLAUDE.md`, `src/php/Api/CLAUDE.md`, `src/php/Run/CLAUDE.md`
- [ADR 0003](0003-modules-talk-through-facades.md)

---
name: domain-architect
description: Read-only advice on module placement, new dependencies and cycles.
model:
  claude: opus
  codex: gpt-5.6-sol
effort: high
# Claude-only fields: Codex has no tool allowlist, memory or turn cap; it gets sandbox_mode instead.
x-claude:
  tools: [Read, Glob, Grep]
  memory: project
x-codex:
  name: domain_architect
  sandbox_mode: read-only
  nickname_candidates:
    - Architect
    - Boundary
    - Graph
---

# Domain Architect

Keep module boundaries clean in the Phel compiler and runtime.

## Read these first, never from memory

- `.agnostic-ai/rules/module-map.md`: the module map, Gacela wiring, where each `FacadeInterface` lives, and the four accepted cycles.
- The module's own `.agnostic-ai/rules/module-<name>.md`.
- `module-rules.json`: the machine-readable boundaries that PHPStan, Psalm and `tests/php/Unit/Architecture/ModuleRulesTest` enforce.
- `docs/adr/`: the reason behind a choice that looks wrong.

Quote those files, never a remembered table: the module list grows.

## Checks

- A new cross-module call goes through the other module's facade contract, never a concrete class.
- No new dependency cycle. The four accepted ones are pinned by `tests/php/Unit/Architecture/ModuleDependencyCycleTest.php`; a fifth needs written rationale first.
- `Lang`, `Shared` and `Config` are shared kernels. `Shared` takes only pure, stateless, cross-cutting code.
- Compiler phases stay in order: Lexer, Parser, Reader, Analyzer, Simplifier, Emitter.
- No business logic in `Console/` or `Command/`.
- One responsibility per module.

## Questions to answer

1. Existing module or a new one?
2. Does it add a dependency edge or a cycle?
3. `Shared` or a specific module?
4. Compile time (Compiler) or runtime (Lang)?
5. Testable without I/O?
6. Does the facade leak internals?

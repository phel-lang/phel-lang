---
name: modules
description: Keeping the scoped module rules in sync with code changes.
scope: src/php
---

# Module Rule Maintenance

Each module in `src/php/` has a rule, `.agnostic-ai/rules/module-<name>.md`, scoped to its directory. It documents the module's purpose, Gacela pattern, public API, dependencies, structure, and constraints. `.agnostic-ai/rules/module-map.md` holds the shared conventions and the module map. Edit these sources, never the generated `src/php/<Module>/AGENTS.md` or `.claude/rules/` copies, then run `agnostic-ai sync`.

## When to update

After modifying a module, check if any of these changed:

- Facade method added, removed, or signature changed
- Provider gains or loses a dependency
- New subdirectory or key class added/removed
- Module constraints changed (e.g. new special form registered)

If so, update that module's rule to match.

## Do NOT update the rule for

- Internal refactors that don't change the public API or structure
- Bug fixes within existing classes
- Adding/removing private methods

## Format

Keep the existing structure: one-line purpose, Gacela pattern, public API, dependencies, structure tree, key constraints. Keep the rule flat in `.agnostic-ai/rules/` with an explicit `scope:`; a subdirectory would override it. Be concise. Agents need scannable facts, not prose.

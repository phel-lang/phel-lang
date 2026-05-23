---
description: Rules for keeping module CLAUDE.md files in sync with code changes
globs: src/php/*/*.php,src/php/*/CLAUDE.md
---

# Module CLAUDE.md Maintenance

Each module in `src/php/` has a `CLAUDE.md` file that documents its purpose, Gacela pattern, public API, dependencies, structure, and constraints.

## When to update

After modifying a module, check if any of these changed:

- Facade method added, removed, or signature changed
- DependencyProvider gains or loses a dependency
- New subdirectory or key class added/removed
- Module constraints changed (e.g. new special form registered)

If so, update that module's `src/php/<Module>/CLAUDE.md` to match.

## Do NOT update CLAUDE.md for

- Internal refactors that don't change the public API or structure
- Bug fixes within existing classes
- Adding/removing private methods

## Format

Keep the existing structure: one-line purpose, Gacela pattern, public API, dependencies, structure tree, key constraints. Be concise — agents need scannable facts, not prose.

---
name: module-docs-sync
description: Fixes drift between module rules and module code.
model: balanced
can: [read, write, edit, Glob, Grep]
x-claude:
  memory: project
x-codex:
  name: module_docs_sync
---

# Module Docs Sync

Audits module rules, `.agnostic-ai/rules/module-<name>.md` (scoped to `src/php/<Module>`), against the actual module code and fixes drift. Edit the rule, never the generated `src/php/<Module>/AGENTS.md` or `.claude/rules/` copy. Return touched source paths to the parent; it owns validation and sync after collecting edits.

Read `.agnostic-ai/rules/agent-tooling.md` before editing. Audit only the assigned modules; scan all modules when explicitly requested.

## Audit Checklist (per module)

For each assigned module directory in `src/php/`:

1. **Rule exists**: if not, create `.agnostic-ai/rules/module-<name>.md` in the format given in `.agnostic-ai/rules/module-map.md`
2. **Purpose line**: still accurate?
3. **Gacela pattern**: Facade, Factory, Config, Provider class names match actual files
4. **Public API**: every public method on the Facade is listed; removed methods are gone
5. **Dependencies**: `#[Provides(...)]` keys and factory `getProvidedDependency(...)` calls match what's actually injected
6. **Structure tree**: subdirectories and key classes match reality
7. **Key constraints**: still accurate, no stale references

## How to check

- Read the Facade class → compare methods against the rule's "Public API" section
- Read the Provider class → compare `#[Provides(...)]` entries against "Dependencies" section
- Glob the module directory → compare structure against "Structure" section
- Read the Factory → verify key classes mentioned still exist

## Output format

For each module, report one of:
- **OK**: no changes needed
- **Updated**: list what changed

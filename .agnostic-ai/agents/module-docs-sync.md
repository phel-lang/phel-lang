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

Audits each assigned module rule, `.agnostic-ai/rules/module-<name>.md`, against the module code and fixes drift. Edit the rule, never a generated copy. Audit all modules only when asked. Return touched paths; the parent owns validation and sync.

Read `.agnostic-ai/rules/module-map.md` (what belongs in a module rule) and `.agnostic-ai/rules/agent-tooling.md` before editing.

## Checklist per module

1. **Rule exists**: if not, create it in the format in `.agnostic-ai/rules/module-map.md`.
2. **Purpose line** is still accurate.
3. **Gacela pattern**: Facade, Factory, Config and Provider names match the files.
4. **Public API**: every public Facade method is listed; removed ones are gone.
5. **Dependencies**: Provider `#[Provides(...)]` keys and Factory `getProvidedDependency(...)` calls match the rule.
6. **Structure**: subdirectories and key classes match a glob of the module.
7. **Key constraints**: no stale references.

## Output format

For each module, report one of:
- **OK**: no changes needed
- **Updated**: list what changed

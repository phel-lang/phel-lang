---
name: module-docs-sync
description: Fixes drift between module rules and module code.
model:
  claude: haiku
  codex: gpt-5.4-mini
memory: project
tools: [Read, Write, Edit, Glob, Grep]
x-codex:
    model_reasoning_effort: medium
    name: module_docs_sync
    nickname_candidates:
        - Docs
        - Sync
        - Notes
---

# Module Docs Sync

Audits every module rule, `.agnostic-ai/rules/module-<name>.md` (scoped to `src/php/<Module>`), against the actual module code and fixes drift. Edit the rule, never the generated `src/php/<Module>/AGENTS.md` or `.claude/rules/` copy, then run `agnostic-ai sync`.

## Audit Checklist (per module)

For each module directory in `src/php/`:

1. **Rule exists**: if not, create `.agnostic-ai/rules/module-<name>.md` in the format of `.agnostic-ai/rules/modules.md`
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

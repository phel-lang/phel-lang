---
name: module-docs-sync
description: Audits and updates CLAUDE.md files in src/php/ modules to match the actual code. Use after large refactors, new modules, or periodic maintenance.
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

Audits every `src/php/<Module>/CLAUDE.md` file against the actual module code and fixes drift.

## Audit Checklist (per module)

For each module directory in `src/php/`:

1. **CLAUDE.md exists** — if not, create one following the standard format
2. **Purpose line** — still accurate?
3. **Gacela pattern** — Facade, Factory, Config, Provider class names match actual files
4. **Public API** — every public method on the Facade is listed; removed methods are gone
5. **Dependencies** — `#[Provides(...)]` keys and factory `getProvidedDependency(...)` calls match what's actually injected
6. **Structure tree** — subdirectories and key classes match reality
7. **Key constraints** — still accurate, no stale references

## How to check

- Read the Facade class → compare methods against CLAUDE.md "Public API" section
- Read the Provider class → compare `#[Provides(...)]` entries against "Dependencies" section
- Glob the module directory → compare structure against "Structure" section
- Read the Factory → verify key classes mentioned still exist

## Output format

For each module, report one of:
- **OK** — no changes needed
- **Updated** — list what changed

## Standard CLAUDE.md format

```markdown
# <Module> Module

One-line purpose.

## Gacela Pattern
(or "## No Gacela Pattern" for leaf modules)

## Public API (Facade)

## Dependencies

## Structure

## Key Constraints
```

Keep content concise and scannable. No prose paragraphs — use lists, tables, and code blocks.

---
description: Read-only design review of a file or directory.
argument-hint: "[file-or-directory]"
context: fork
agent: Explore
x-claude:
  allowed-tools: "Read, Glob, Grep"
x-codex:
  interface:
    display_name: Refactor Check
---

# Refactor Check

Read and analyze the specified file(s) from the argument.

## SOLID Violations

| Principle | Key Symptoms |
|-----------|--------------|
| SRP | Class has many methods, hard to name without "And"/"Manager" |
| OCP | Switch/if-else chains that grow with features |
| LSP | `instanceof` checks, overridden methods that break behavior |
| ISP | Empty method implementations, "not implemented" exceptions |
| DIP | `new` in business logic, hard to test without file system |

## Clean Code Issues

- **Naming**: Descriptive and intention-revealing?
- **Functions**: Small (< 20 lines)? One responsibility? ≤ 3 args?
- **Comments**: Explain "why" not "what"? No commented-out code?
- **Errors**: Specific exceptions? Fail fast?

## Architecture Compliance

Check the file against `.agnostic-ai/rules/module-map.md` and its module's `.agnostic-ai/rules/module-<name>.md`: cross-module calls go through facade contracts, `Shared` holds only pure cross-cutting code, and no new dependency cycle appears. For compiler code, phases stay in order per `.agnostic-ai/rules/compiler.md`.

## For Phel Source Files (`src/phel/`)

- kebab-case naming?
- `:doc` metadata present?
- `:see-also` references as strings?
- Clojure-aligned semantics?

## Output Format

```markdown
# Refactor Analysis: <file/directory>

## Summary
- **SOLID Violations:** X issues
- **Clean Code Issues:** X issues
- **Architecture Issues:** X issues

## Critical Issues (High Priority)
### [SRP] <Class> has multiple responsibilities
**File:** `src/php/Module/Class.php:10-50`
**Problem:** ...
**Suggestion:** ...

## Moderate Issues (Medium Priority)
...

## Minor Issues (Low Priority)
...

## Recommended Refactoring Steps
1. ...
```

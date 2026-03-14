---
description: Analyze code for SOLID violations, clean code issues, and architecture compliance
argument-hint: "[file-or-directory]"
context: fork
agent: Explore
allowed-tools: "Read, Glob, Grep"
---

# Refactor Check

Read and analyze the specified file(s) from `$ARGUMENTS`.

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

| Check | Rule |
|-------|------|
| Lang independence | `Lang/` has zero deps on other modules |
| Module boundaries | Cross-module access only via Facades |
| Shared scope | `Shared/` contains only genuinely cross-cutting code |
| Compiler phases | No phase skipping (Lexer → Parser → Analyzer → Emitter) |

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

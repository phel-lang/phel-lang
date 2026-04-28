---
name: refactor-check
description: Analyze code for SOLID, clean-code, and Phel architecture issues. Use when Codex is asked to review refactoring opportunities, inspect a file or directory for design problems, or plan a cleanup pass.
---

# Refactor Check

## Analyze

Read the requested file or directory. If none is provided, inspect the current branch diff against `main`.

## SOLID Symptoms

| Principle | Symptoms |
|-----------|----------|
| SRP | Class has many unrelated methods or an unclear responsibility |
| OCP | Switch or if-else chains that grow with every feature |
| LSP | `instanceof` checks or overrides that weaken behavior |
| ISP | Empty methods or "not implemented" interface methods |
| DIP | Business logic directly constructs hard-to-test dependencies |

## Clean Code Checks

- Names are descriptive and intention-revealing.
- Functions are small, focused, and usually have three or fewer arguments.
- Comments explain why, not what.
- Exceptions are specific and failures happen early.
- Tests cover behavior instead of implementation trivia.

## Architecture Checks

| Area | Rule |
|------|------|
| `Lang/` | No dependencies on other modules |
| Module boundaries | Cross-module access only via Facades |
| `Shared/` | Only genuinely cross-cutting code |
| Compiler | Preserve Lexer -> Parser -> Analyzer -> Emitter phase order |

## Phel Source Checks

- kebab-case names
- public metadata includes `:doc`, `:see-also`, and `:example`
- `:see-also` values are strings
- semantics stay aligned with Clojure when intended

## Output Format

```markdown
# Refactor Analysis: <file/directory>

## Summary
- **SOLID Violations:** X issues
- **Clean Code Issues:** X issues
- **Architecture Issues:** X issues

## Critical Issues
### [SRP] <Class> has multiple responsibilities
**File:** `src/php/Module/Class.php:10`
**Problem:** ...
**Suggestion:** ...

## Moderate Issues
...

## Minor Issues
...

## Recommended Refactoring Steps
1. ...
```

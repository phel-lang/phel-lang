# Refactor Check

Analyze code for SOLID violations, clean code issues, and architecture compliance.

## Arguments
- `$ARGUMENTS` - File path or directory to analyze

## Instructions

1. **Read the specified file(s)** from `$ARGUMENTS`

2. **Analyze for SOLID violations**:

   | Principle | Key Symptoms |
   |-----------|--------------|
   | SRP | Class has many methods, hard to name without "And"/"Manager" |
   | OCP | Switch/if-else chains that grow with features |
   | LSP | `instanceof` checks, overridden methods that break behavior |
   | ISP | Empty method implementations, "not implemented" exceptions |
   | DIP | `new` in business logic, hard to test without file system |

3. **Analyze for Clean Code issues**:

   - **Naming**: Descriptive and intention-revealing?
   - **Functions**: Small (< 20 lines)? One responsibility? ≤ 3 args?
   - **Comments**: Explain "why" not "what"? No commented-out code?
   - **Errors**: Specific exceptions? Fail fast?

4. **Check Module Architecture compliance**:

   | Check | Rule |
   |-------|------|
   | Lang independence | `Lang/` has zero deps on other modules |
   | Module boundaries | Cross-module access only via Facades |
   | Shared scope | `Shared/` contains only genuinely cross-cutting code |
   | Compiler phases | No phase skipping (Lexer → Parser → Analyzer → Emitter) |

5. **For Phel source files** (`src/phel/`):
   - kebab-case naming?
   - `:doc` metadata present?
   - `:see-also` references as strings?
   - Clojure-aligned semantics?

6. **Generate report**:

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

## Checklist
- [ ] File(s) read and analyzed
- [ ] SOLID violations identified
- [ ] Clean code issues identified
- [ ] Architecture compliance checked
- [ ] Report generated with actionable suggestions

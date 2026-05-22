# Phel Language

Functional programming language compiling to PHP. Lisp dialect inspired by Clojure.

## Architecture

```
src/php/       → Compiler, runtime, CLI (PHP, PSR-4: Phel\)
src/phel/      → Core library (Phel source: core, string, html, http, json, test)
tests/php/     → PHPUnit tests (unit + integration)
tests/phel/    → Phel test files
build/         → PHAR build scripts, release tooling
```

Each module in `src/php/` has a `CLAUDE.md` — **read it before modifying a module**. It documents the Gacela pattern, public API, dependencies, and constraints.

## Skills (slash commands)

Authoritative for their topic — load the skill before guessing. Match the workflow first, then fall back to plain commands.

- `/test [scope]` → run tests, scope mapping (`.claude/skills/test/SKILL.md`)
- `/commit [msg]` → quality gates + conventional commit (`.claude/skills/commit/SKILL.md`)
- `/pr [issue]` → push + open PR from template (`.claude/skills/pr/SKILL.md`)
- `/changelog [entry|--optimize]` → update `## Unreleased`; auto-run `/changelog --optimize` after any `CHANGELOG.md` edit (`.claude/skills/changelog/SKILL.md`)
- `/fix`, `/refactor-check`, `/benchmark`, `/integration-fixture`, `/module-new`, `/gh-issue`, `/release` → other workflows under `.claude/skills/`

## Conventions

- Conventional commits: `feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:` (full rules in `/commit`)
- Branch prefixes match the commit type: `feat/`, `fix/`, `ref/`, `docs/`
- Never mention AI tooling in commit messages, PR bodies, or code comments
- Module-specific rules live in `.claude/rules/*.md` (php, phel, compiler, modules, macro-hygiene, integration-tests)
- After editing `CHANGELOG.md`, run `/changelog --optimize` to enforce style (cap 120 chars, cluster Performance by theme, merge sibling PRs, drop filler)

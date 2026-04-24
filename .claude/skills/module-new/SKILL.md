---
description: Scaffold a new Gacela module under src/php/ with Facade, DependencyProvider, and CLAUDE.md
argument-hint: "<ModuleName>"
disable-model-invocation: true
allowed-tools: "Read, Write, Edit, Glob, Bash(ls *), Bash(composer *)"
---

# New Gacela Module

Scaffolds a new module under `src/php/<ModuleName>/` following the project Gacela pattern.

## Context

!`ls src/php/`

## Instructions

1. **Validate `$ARGUMENTS`**: must be PascalCase, not clash with an existing dir. If missing, ask.

2. **Read a reference module** (pick a small one, e.g. `src/php/Printer/` or `src/php/Formatter/`) to mirror its layout. Record:
   - Facade method shape
   - DependencyProvider constant names
   - CLAUDE.md section order

3. **Create the following files** under `src/php/<ModuleName>/`:
   ```
   <ModuleName>Facade.php          # final class extending \Gacela\Framework\AbstractFacade
   <ModuleName>Factory.php         # final class extending AbstractFactory (only if module needs internal wiring)
   <ModuleName>DependencyProvider.php  # only if the module depends on another module's Facade
   Domain/                         # pure business logic (no framework deps)
   Infrastructure/                 # adapters, CLI commands, IO
   CLAUDE.md                       # one-line purpose, Gacela pattern, public API, deps, structure, constraints
   ```

4. **Facade contract**: every public call must return from the Factory; never instantiate dependencies inline in the Facade.

5. **CLAUDE.md template** (keep scannable — no prose):
   ```markdown
   # <ModuleName>

   <one-line purpose>

   ## Gacela pattern

   Facade → Factory → Domain

   ## Public API

   - `<ModuleName>Facade::method()` — <one line>

   ## Dependencies

   - <OtherModule>Facade (via DependencyProvider)

   ## Structure

   <ModuleName>/
     Domain/
     Infrastructure/

   ## Constraints

   - <key invariant or rule>
   ```

6. **Do not** register the module anywhere — Gacela auto-discovers via PSR-4.

7. **Run static analysis** on the new files only:
   ```bash
   composer test-quality
   ```

## Constraints

- No classes instantiated across module boundaries — always go through another module's Facade.
- Mark classes `final` unless inheritance is explicitly justified.
- Use `readonly` properties where possible (per `.claude/rules/php.md`).

---
description: Scaffold a new Gacela module under src/php/ with Facade, Provider, and CLAUDE.md
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

2. **Read a reference module** (pick a small one, e.g. `src/php/Filesystem/` or `src/php/Formatter/`) to mirror its layout. Record:
   - Facade method shape
   - `#[ServiceMap(...)]` pillar mappings
   - `#[Provides(...)]` dependency keys
   - CLAUDE.md section order

3. **Create the following files** under `src/php/<ModuleName>/`:
   ```
   <ModuleName>Facade.php          # final class extending \Gacela\Framework\AbstractFacade
   <ModuleName>Factory.php         # final class extending AbstractFactory (only if module needs internal wiring)
   <ModuleName>Provider.php           # only if the module depends on another module's Facade
   Domain/                         # pure business logic (no framework deps)
   Infrastructure/                 # adapters, CLI commands, IO
   CLAUDE.md                       # one-line purpose, Gacela pattern, public API, deps, structure, constraints
   ```

4. **Facade contract**: every public call must return from the Factory; never instantiate dependencies inline in the Facade.

5. **Explicit pillar services**: import `Gacela\Framework\ServiceResolver\ServiceMap` and declare:
   - Facade: `#[ServiceMap(method: 'getFactory', className: <ModuleName>Factory::class)]`
   - Factory: `#[ServiceMap(method: 'getConfig', className: <ModuleName>Config::class)]`
   - Provider: the same `getConfig` mapping; use `AbstractConfig::class` only when the module intentionally has no custom Config

6. **CLAUDE.md template** (keep scannable — no prose):
   ```markdown
   # <ModuleName>

   <one-line purpose>

   ## Gacela pattern

   Facade → Factory → Domain

   ## Public API

   - `<ModuleName>Facade::method()` — <one line>

   ## Dependencies

   - `<OtherFacadeInterface>::class` (via Provider)

   ## Structure

   <ModuleName>/
     Domain/
     Infrastructure/

   ## Constraints

   - <key invariant or rule>
   ```

7. **Do not** register the module anywhere — Gacela auto-discovers via PSR-4.

8. **Run static analysis** on the new files only:
   ```bash
   composer test-quality
   ```

## Constraints

- No classes instantiated across module boundaries — always go through another module's Facade.
- Do not rely on Gacela's deprecated source/docblock service-resolution fallback; every inherited pillar accessor has an explicit `#[ServiceMap]`.
- Provider entries use Gacela 2.0 `#[Provides(...)]`; key facade dependencies by the consumer-facing interface when one exists.
- Mark classes `final` unless inheritance is explicitly justified.
- Use `readonly` properties where possible (per `.claude/rules/php.md`).

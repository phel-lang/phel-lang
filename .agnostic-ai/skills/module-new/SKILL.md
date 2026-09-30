---
description: Scaffold a new Gacela module with its scoped rule.
argument-hint: "<ModuleName>"
disable-model-invocation: true
x-claude:
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
   - the section order of its rule, `.agnostic-ai/rules/module-<name>.md`

3. **Create the following files** under `src/php/<ModuleName>/`:
   ```
   <ModuleName>Facade.php          # final class extending \Gacela\Framework\AbstractFacade
   <ModuleName>Factory.php         # final class extending AbstractFactory (only if module needs internal wiring)
   <ModuleName>Provider.php        # only if the module depends on another module's Facade
   Domain/                         # pure business logic (no framework deps)
   Infrastructure/                 # adapters, CLI commands, IO
   ```

4. **Facade contract**: every public call must return from the Factory; never instantiate dependencies inline in the Facade.

5. **Explicit pillar services**: import `Gacela\Framework\ServiceResolver\ServiceMap` and declare:
   - Facade: `#[ServiceMap(method: 'getFactory', className: <ModuleName>Factory::class)]`
   - Factory: `#[ServiceMap(method: 'getConfig', className: <ModuleName>Config::class)]`
   - Provider: the same `getConfig` mapping; use `AbstractConfig::class` only when the module intentionally has no custom Config

6. **Module rule**: create `.agnostic-ai/rules/module-<kebab-name>.md` in the format of `.agnostic-ai/rules/modules.md`, and add the module to the map in `.agnostic-ai/rules/module-map.md`. Document only what the code does not say. Run `agnostic-ai sync`.

7. **Registration**: Gacela discovers the module through PSR-4. A module with CLI commands also needs a `<ModuleName>Commands` class in `src/php/Console/Infrastructure/Command/`, listed in `ConsoleProvider::commandProviders()`.

8. **Check**: `composer test-quality` (whole project) and `./vendor/bin/phpunit tests/php/Unit/Architecture`.

## Constraints

- No classes instantiated across module boundaries: always go through another module's Facade.
- Do not rely on Gacela's deprecated source/docblock service-resolution fallback; every inherited pillar accessor has an explicit `#[ServiceMap]`.
- Provider entries use Gacela 2.0 `#[Provides(...)]`; key facade dependencies by the consumer-facing interface when one exists.
- Mark classes `final` unless inheritance is explicitly justified.
- Use `readonly` properties where possible (per `.agnostic-ai/rules/php.md`).

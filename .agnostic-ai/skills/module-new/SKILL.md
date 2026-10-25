---
description: Scaffold a new Gacela module with its scoped rule.
argument-hint: "<ModuleName>"
disable-model-invocation: true
allowed-tools:
  - "read"
  - "write"
  - "edit"
  - "Glob"
  - "shell(ls *)"
  - "shell(composer *)"
  - "shell(./vendor/bin/phpunit *)"
  - "shell(agnostic-ai *)"
---

# New Gacela Module

Scaffolds a new module under `src/php/<ModuleName>/` following the project Gacela pattern.

## Context

Run first: `ls src/php/`.

## Instructions

1. **Validate the argument**: must be PascalCase, not clash with an existing dir. If missing, ask.

2. **Read `.agnostic-ai/rules/module-map.md`**, then a small reference module (e.g. `src/php/Filesystem/` or `src/php/Formatter/`) to mirror its layout. Record:
   - Facade method shape
   - `#[ServiceMap(...)]` pillar mappings
   - `#[Provides(...)]` dependency keys
   - the section order of its rule, `.agnostic-ai/rules/module-<name>.md`

3. **Create the following files** under `src/php/<ModuleName>/`:
   ```
   <ModuleName>Facade.php          # final class extending \Gacela\Framework\AbstractFacade
   <ModuleName>Factory.php         # final class extending AbstractFactory
   <ModuleName>Provider.php        # only if the module depends on another module's Facade
   <ModuleName>Config.php          # extends AbstractConfig, only for module-specific configuration
   Domain/                         # pure business logic (no framework deps)
   Infrastructure/                 # adapters, CLI commands, IO
   ```

4. **Facade contract**: every public call must return from the Factory; never instantiate dependencies inline in the Facade.

5. **Explicit pillar services**: import `Gacela\Framework\ServiceResolver\ServiceMap` and declare:
   - Facade: `#[ServiceMap(method: 'getFactory', className: <ModuleName>Factory::class)]`
   - Every Factory and Provider: map `getConfig` to `<ModuleName>Config::class` when it exists, otherwise `AbstractConfig::class`, including when the accessor is inherited. Match the Factory generic to that config class. Never reference a class the scaffold did not create.

6. **Module rule**: create `.agnostic-ai/rules/module-<kebab-name>.md` in the format given in `.agnostic-ai/rules/module-map.md`, and add the module to the table in `docs/internals/architecture.md`. Document only what the code does not say. Run `agnostic-ai sync`.

7. **Registration**: Gacela discovers the module through PSR-4. A module with CLI commands also needs a `<ModuleName>Commands` class in `src/php/Console/Infrastructure/Command/`, listed in `ConsoleProvider::commandProviders()`.

8. **Check**: use `./vendor/bin/phpunit tests/php/Unit/Architecture` for boundary checks, then run the final gate in `.agnostic-ai/rules/build-test-and-development-commands.md`.

## Constraints

- No classes instantiated across module boundaries: always go through another module's Facade.
- Do not rely on Gacela's deprecated source/docblock service-resolution fallback; every inherited pillar accessor has an explicit `#[ServiceMap]`.
- Provider entries use Gacela 2.0 `#[Provides(...)]`; key facade dependencies by the consumer-facing interface when one exists.
- Mark classes `final` unless inheritance is explicitly justified.
- Use `readonly` properties where possible (per `.agnostic-ai/rules/php.md`).

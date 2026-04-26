---
name: module-new
description: Scaffold a new Phel PHP Gacela module. Use when Codex is asked to create a module under src/php with Facade, Factory, DependencyProvider, Domain/Infrastructure structure, and module CLAUDE.md notes.
---

# New Gacela Module

## Workflow

1. Validate the requested module name:
   - PascalCase
   - no existing directory under `src/php/`
   - clear module responsibility

2. Read a small reference module, such as `src/php/Printer/` or `src/php/Formatter/`, and mirror local patterns.

3. Create only the files the module needs:
   ```text
   src/php/<ModuleName>/<ModuleName>Facade.php
   src/php/<ModuleName>/<ModuleName>Factory.php
   src/php/<ModuleName>/<ModuleName>DependencyProvider.php
   src/php/<ModuleName>/Domain/
   src/php/<ModuleName>/Infrastructure/
   src/php/<ModuleName>/CLAUDE.md
   ```

4. Keep the Facade as the public module boundary. Every public Facade call should delegate through the Factory; do not instantiate dependencies inline in the Facade.

5. Use this `CLAUDE.md` shape for module notes:
   ```markdown
   # <ModuleName>

   <one-line purpose>

   ## Gacela pattern

   Facade -> Factory -> Domain

   ## Public API

   - `<ModuleName>Facade::method()` - <one line>

   ## Dependencies

   - <OtherModule>Facade (via DependencyProvider)

   ## Structure

   <ModuleName>/
     Domain/
     Infrastructure/

   ## Constraints

   - <key invariant or rule>
   ```

6. Do not register the module manually; Gacela discovers via PSR-4.

7. Run focused quality checks, typically:
   ```bash
   composer test-quality
   ```

## Constraints

- Cross-module access goes through Facades.
- Mark classes `final` unless inheritance is required.
- Use `readonly` properties where possible.

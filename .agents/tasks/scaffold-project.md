# Task: Scaffold new Phel project

## Goal

Bootstrap runnable Phel project with tests in < 30s.

## Prereqs

- PHP 8.3+ installed
- Composer installed
- Writable target directory

## Steps

### 1. Install Phel

```bash
composer require phel-lang/phel-lang
```

### 2. Scaffold

Default layout (nested: `src/phel/`, `tests/phel/`):

```bash
./vendor/bin/phel init my-app
```

Flat layout (single-level `src/`, `tests/`):

```bash
./vendor/bin/phel init my-app --flat
```

Minimal layout (single `main.phel` at repo root — sandbox only):

```bash
./vendor/bin/phel init my-app --minimal
```

### 3. Verify

```bash
./vendor/bin/phel run src/phel/main.phel   # default layout
./vendor/bin/phel test                     # runs tests
./vendor/bin/phel repl                     # interactive
```

Expected: `Hello, World!` printed; one test passes.

## Layouts — when to use

| Flag | Files | Use when |
|------|-------|----------|
| (default) | `src/phel/main.phel`, `tests/phel/main_test.phel`, `phel-config.php` | Most apps; matches published convention |
| `--flat` | `src/main.phel`, `tests/main_test.phel`, `phel-config.php` | Shorter paths; integrating into existing PHP project |
| `--minimal` | `main.phel`, `main_test.phel`, `phel-config.php` | One-file experiments, sandbox, demos |

## Generated files

- `phel-config.php` — autodetected layout + namespace via `PhelConfig::forProject()`
- `src/phel/main.phel` (or equivalent) — starter namespace
- `tests/phel/main_test.phel` — one passing test
- `.gitignore` — excludes `cache/`, `out/`, `vendor/`

## Flags

| Flag | Purpose |
|------|---------|
| `--flat`, `-f` | Flat layout |
| `--minimal`, `-m` | Root layout |
| `--no-tests` | Skip test scaffold |
| `--no-gitignore` | Skip `.gitignore` |
| `--dry-run` | Print plan, write nothing |
| `--force` | Overwrite existing files |

## Gotchas

- Namespace needs ≥ 2 segments: `my-app\main`, not `main`. `phel init` handles this.
- If `phel-config.php` exists, use `--force` or write manually.
- Inside existing PHP project: use `--flat` and keep `phel-config.php` at project root.

## Next

- Add namespaces under `src/phel/<name>.phel`
- Add matching tests under `tests/phel/<name>_test.phel`
- See `tasks/http-app.md` for web apps
- See `docs/framework-integration.md` for Symfony/Laravel integration

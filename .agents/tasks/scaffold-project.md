# Scaffold a Phel project

```bash
composer require phel-lang/phel-lang
./vendor/bin/phel init my-app              # default: src/, tests/
./vendor/bin/phel init my-app --nested     # src/phel/, tests/phel/
./vendor/bin/phel init my-app --minimal    # single main.phel at root
```

Verify:

```bash
./vendor/bin/phel run src/main.phel
./vendor/bin/phel test
./vendor/bin/phel repl
```

## Flags

| Flag | Purpose |
|------|---------|
| `--nested` | nested layout |
| `--minimal`, `-m` | root layout |
| `--no-tests` | skip test scaffold |
| `--no-gitignore` | skip `.gitignore` |
| `--dry-run` | print plan |
| `--force` | overwrite |

`phel-config.php` optional; `PhelConfig::forProject()` auto-detects layout.

Namespaces need ≥ 2 segments (`my-app\main`, not `main`).

## Shell completion (install skill adapters)

```bash
./vendor/bin/phel agent-install claude   # Claude / Claude Code
./vendor/bin/phel agent-install --all    # every supported platform
```

Installs `.agents/` rules + task recipes + examples into your project so the assistant has Phel context.

## Next

`tasks/http-app.md`, `tasks/cli-tool.md`, `tasks/add-tests.md`, `tasks/use-core-lib.md`

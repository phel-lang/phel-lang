# Contributing to Phel

- [Report a bug](https://github.com/phel-lang/phel-lang/issues/new?labels=bug&template=BUG.md)
- [Propose a feature](https://github.com/phel-lang/phel-lang/issues/new?labels=enhancement&template=FEATURE_REQUEST.md)
- [Open a pull request](https://github.com/phel-lang/phel-lang/pulls)

For bigger changes, open an issue first so we can discuss.

## Understanding the Codebase

Two-language project (compiler/runtime in PHP, standard library in Phel):

```
src/php/      → Compiler, runtime, CLI tools (PHP, Gacela modules)
src/phel/     → Core standard library (Phel source, Clojure-inspired)
tests/php/    → PHPUnit tests
tests/phel/   → Phel tests
docs/         → Guides, examples, internals
```

Compiler pipeline: Lexer → Parser → Reader → Analyzer → Emitter. Each `src/php/` module exposes a Facade as its public API.

For a deeper architecture overview, see [docs/internals/architecture.md](../docs/internals/architecture.md).

## Quick Start

Requires **PHP 8.5+** and **Composer**.

```bash
composer install
COMPOSER_PROCESS_TIMEOUT=0 composer test   # baseline after setup
```

## Making Changes

1. Branch from `main` (prefixes: `feat/`, `fix/`, `ref/`, `docs/`)
2. Write code and tests
3. Run the [targeted test command](#which-tests-to-run) for your change
4. Run `composer fix` to auto-format
5. Open a PR following the [PR template](PULL_REQUEST_TEMPLATE.md)

## Commands

**Day-to-day:**

```bash
COMPOSER_PROCESS_TIMEOUT=0 composer test   # Run everything (quality + compiler + core)
composer fix        # Auto-format (Rector + CS Fixer)
composer create-pr  # Open a PR
```

**Testing:**

```bash
composer test-compiler  # PHPUnit (unit + integration)
composer test-core      # Phel's own test framework
composer test-quality   # Static analysis + linting
```

**Static analysis:**

```bash
composer psalm      # Psalm
composer phpstan    # PHPStan
composer csrun      # Code style check
composer rector     # Rector check
```

**Benchmarks:**

```bash
composer phpbench       # Run benchmarks
composer phpbench-base  # Create baseline
composer phpbench-ref   # Compare to baseline
```

**Build:**

```bash
./build/phar.sh         # Produce build/out/phel.phar
```

## Which Tests to Run

Start with tests for the changed behavior and its affected integration path. Broaden for cross-cutting changes, failures or explicit requirements. Run affected quality checks after reviewing the final diff, and reuse passing results on unchanged code.

| Scope | Command | Notes |
|------------------|---------|-------|
| PHP behavior | `./vendor/bin/phpunit --filter=ClassOrMethod` | Include affected integration tests |
| Single Phel file | `./bin/phel test tests/phel/<file>` | Target specific test |
| Compiler fixture | `./vendor/bin/phpunit --testsuite=integration --filter=fixture-stem` | Generate expected PHP from the actual output |
| Compiler test group | `composer test-compiler` | PHPUnit unit + integration |
| Standard library test group | `composer test-core` | Phel core tests |
| Quality checks | `composer test-quality` | Formatter, static analysis and config; use affected checks when isolating a change |
| Full gate | `COMPOSER_PROCESS_TIMEOUT=0 composer test` | Run when broader coverage is needed |

See the [testing docs](https://phel-lang.org/documentation/testing/) for more on the Phel test framework.

## Git Hooks

Optional but recommended:

```bash
tools/git-hooks/init.sh
```

Installs a pre-commit hook that runs `composer test-all` when PHP or Phel files are staged. Let required hooks complete and reuse a successful gate on unchanged code. Fixture-only changes still need the selected checks because `.test` files do not trigger this hook.

## Commit Messages

We use [conventional commits](https://www.conventionalcommits.org/):

```
feat: add new string utility function
fix: correct off-by-one in map iteration
ref: simplify compiler analyzer phase
docs: update quickstart guide
test: add integration tests for try/catch
chore: bump phpstan to v2
```

## Changelog

For user-facing changes (`feat:`, `fix:`), update `CHANGELOG.md` under `## Unreleased`. Sections come in this order, each once: `Changed`, `Added`, `Removed`, `Performance`, `Fixed`. Write short entries that say what the user sees, merge bullets about the same change, and end with the refs: `(#1234)`. Full style: [`.agnostic-ai/rules/changelog.md`](../.agnostic-ai/rules/changelog.md).

## AI Tooling

[.agnostic-ai/](../.agnostic-ai/) is the single source for the agent config used on this repository. [agnostic-ai](https://github.com/Chemaclass/agnostic-ai) generates Claude Code (`.claude/`, `CLAUDE.md`) and Codex (`.codex/`, `.agents/`, `AGENTS.md`) from it. The generated files are gitignored, so run `sync` after cloning and after every spec change. `agnostic-ai.yaml` pins stable 0.90.0 to match CI; `agnostic-ai upgrade --version v0.90.0` installs it.

Each PHP module is documented by a rule scoped to its directory, `.agnostic-ai/rules/module-<name>.md`. Sync emits it as `src/php/<Module>/AGENTS.md` for Codex and as a path-scoped rule under `.claude/rules/` for Claude Code. Edit the rule, not the output.

```bash
brew install Chemaclass/tap/agnostic-ai   # or: go install github.com/chemaclass/agnostic-ai/cmd/agnostic-ai@v0.90.0
agnostic-ai sync
```

Edit the specs under `.agnostic-ai/`, never the generated files. Add a target (Gemini, Cursor, ...) under `targets:` in `agnostic-ai.yaml`. Run `agnostic-ai validate`, `agnostic-ai lint --strict`, `agnostic-ai sync`, and `agnostic-ai sync --check` after spec changes. CI runs the same checks. For scope changes, inspect a representative file with `agnostic-ai explain --file <path> --target codex` and `--target claude`; for instruction size, use `agnostic-ai explain --context --target <target>`.

## Where to Start

Issues labeled [`good first issue`](https://github.com/phel-lang/phel-lang/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22) are scoped and newcomer-friendly.

| Interest | Start here | Tests |
|----------|-----------|-------|
| Fix/add a language feature | `src/php/Compiler/` | `composer test-compiler` |
| Improve the standard library | `src/phel/` | `composer test-core` |
| Improve tooling (REPL, formatter) | `src/php/Run/`, `src/php/Formatter/` | `composer test-compiler` |
| Improve documentation | `docs/` | none |
| Add PHP interop wrappers | `src/php/Interop/` | `composer test-compiler` |

## Troubleshooting

**`composer test` fails after fresh clone?** Check PHP 8.5+ (`php -v`) and run `composer install` again.

**Tests pass locally but CI fails?** Inspect the failing job and reproduce its affected test or group. Broaden local coverage when the failure shows the initial scope was incomplete.

**Code style errors?** Run `composer fix`.

---

[Code of Conduct](CODE_OF_CONDUCT.md) · [MIT License](https://github.com/phel-lang/phel-lang/blob/master/LICENSE) · [Discussions](https://github.com/phel-lang/phel-lang/discussions)

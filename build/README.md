# build/

Phel artifact production. Everything here exists to turn the source tree into
something shippable (today: a single-file PHAR). Developer tooling that
operates *on* the project (release, ecosystem upgrade, agent validation,
bashunit, git hooks) lives in [`../tools/`](../tools/README.md).

## Files

| File | Purpose |
|---|---|
| [`phar.sh`](phar.sh) | Bash entry point. Sets up the build sandbox and invokes `build-phar.php`. |
| [`build-phar.php`](build-phar.php) | Compiles the PHAR: collects sources, applies the preload, writes `out/phel.phar`. |
| [`preload.php`](preload.php) | PHAR bootstrap. Wires autoload + runtime before user code runs. |
| `out/` | PHAR output. Gitignored. |
| `.phar-cache/` | Composer/PHAR build cache. Gitignored. |

## Build

```bash
./build/phar.sh
# -> build/out/phel.phar
```

CI runs the same command (see `.github/workflows/ci.yml`) and smoke-tests the
resulting PHAR. Release flow is driven from
[`../tools/release.sh`](../tools/release.sh); it invokes `phar.sh` and uploads
`build/out/phel.phar` to the GitHub release.

## Not here

- Release orchestration → [`../tools/release.sh`](../tools/release.sh)
- Ecosystem bumps → [`../tools/upgrade-ecosystem.sh`](../tools/upgrade-ecosystem.sh)
- Agent validation → [`../tools/validate-agents.sh`](../tools/validate-agents.sh)
- bashunit tests → [`../tools/*-test.sh`](../tools/README.md)

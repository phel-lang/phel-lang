# Web Playground: language-side design and spike

Design notes for a "try Phel in the browser" playground
([#2696](https://github.com/phel-lang/phel-lang/issues/2696)). The **UI** (editor,
output pane, permalinks, doc "try it" widgets) belongs in the
[phel-lang.org](https://phel-lang.org) repo. This page tracks the language-side
prerequisites. It is a spike, not a shipped feature.

## The eval building block

`phel eval` (alias `e`, `src/php/Run/Infrastructure/Command/EvalCommand.php`)
loads the core namespaces, compiles the input through the normal pipeline,
evaluates the emitted PHP in memory (`InMemoryEvaluator`, PHP `eval()`), and
prints the result. Inline argument or stdin with `-`:

```console
$ phel eval '(+ 1 2)'
3
$ echo '(* 6 7)' | phel eval -
42
```

That is ~80% of a server-side sandbox primitive: stateless, string in, value out.
What it does not do is isolate anything.

## The gap to a safe playground

`phel eval` runs with the full power of the host process. Two categories of escape
hatch:

1. **`php/*` interop forms** (`NAME_PHP_*` in `src/php/Lang/Symbol.php`) reach
   arbitrary PHP: `phel eval '(php/getenv "HOME")'`.
2. **Core functions that do I/O with no `php/*` form at all.** This is the
   important finding: `slurp` / `spit` (`src/phel/core/io.phel`), `load-file`
   (`ns.phel`), `eval` / `read-string` (`protocols.phel`) are plain corelib
   functions.

   ```console
   $ phel eval '(take 20 (slurp "/etc/hosts"))'
   @["#" "#" "\n" "#" " " "H" "o" "s" "t" ...]
   ```

### Why a `--no-interop` flag is not enough

A flag rejecting `php/*` at analysis time was considered and **deliberately not
shipped**. Because `slurp`, `spit`, `load-file` and `eval` are ordinary core
functions, such a flag leaves the biggest escape hatches open while reading as a
safety feature. False confidence is worse than none here. A real allowlist has to
operate on the effective set of callable symbols, not on interop syntax.

### What a real server-side sandbox needs

A dedicated security project, not a compiler flag. At minimum:

- **Symbol allowlist** restricting the callable corelib surface (drop `slurp`,
  `spit`, `load-file`, `eval`, `read-string`, `sh`-style helpers) *and* rejecting
  `php/*`, enforced at analysis time so nothing dangerous reaches the emitter.
- **OS-level isolation**, since the process still runs PHP `eval()`: container
  boundary, no filesystem writes, no network, syscall filtering, read-only root, a
  fresh short-lived process per request.
- **Resource limits**: CPU/wall-clock timeout, memory cap, output-size cap,
  recursion guard.
- **Abuse prevention**: rate limiting, request-size limits, per-IP quotas.

Its own PR with a dedicated security review. Not bolted onto `phel eval`.

## PHP-in-WASM path: GO, pending PoC

Running compiler and runtime client-side via
[php-wasm](https://github.com/seanmorris/php-wasm) sidesteps the server sandbox
entirely: the browser tab is the sandbox. Language-side blockers checked:

- **No hard PHP extension dependencies.** `composer.json` `require` is
  `php >=8.4`, `amphp/amp`, `gacela-project/gacela`, `symfony/console`,
  `symfony/routing`, all pure PHP. The only `ext-*` entry (`ext-readline`) is
  `require-dev` and used solely by the interactive REPL.
- **Eval uses `eval()`, not `proc_open`.** `proc_open`/`pcntl`/`posix`/Fibers
  appear only in the parallel test runner, nREPL server, watcher and async paths,
  none on the compile-and-eval path.

Open items a PoC must verify: that php-wasm's bundled PHP satisfies `>=8.4`; the
payload size once compiler plus core `.phel` are bundled, and cold-start compile
time in the browser; that amp, gacela and symfony load cleanly under php-wasm's
filesystem shim.

## Recommendation

- **Short term:** embed pre-rendered `:example` output in docs from the corelib
  metadata. No live eval, zero risk.
- **Interactive playground:** prefer the WASM path. It removes the entire
  server-sandbox surface that would otherwise each need building and reviewing.
  Gate on the PoC above.
- **If a server API is chosen instead:** treat the sandbox as a standalone,
  security-reviewed project (allowlist *plus* container isolation *plus* resource
  limits) and never present a compiler flag alone as a boundary.

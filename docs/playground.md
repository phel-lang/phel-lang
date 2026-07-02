# Web Playground — Language-Side Design & Spike

Design notes and investigation for a "try Phel in the browser" playground
(issue [#2696](https://github.com/phel-lang/phel-lang/issues/2696)).

> The playground **UI** (editor, output pane, permalinks, doc "try it" widgets)
> belongs in the [phel-lang.org](https://phel-lang.org) repo. This page tracks
> the **language-side prerequisites**: the eval entry point, what a sandbox
> would need, and whether a PHP-in-WASM build is feasible. It is a spike, not a
> shipped feature.

## The eval building block

Phel already ships a one-shot evaluator: the `eval` command (alias `e`), in
`src/php/Run/Infrastructure/Command/EvalCommand.php`. It loads the core
namespaces, compiles the input string through the normal
lex → parse → analyze → emit pipeline, evaluates the emitted PHP in-memory
(`InMemoryEvaluator`, which calls PHP `eval()`), and prints the result.

It accepts an inline argument or reads from stdin with `-`:

```console
$ phel eval '(+ 1 2)'
3

$ echo '(* 6 7)' | phel eval -
42
```

This is ~80% of the "server-side sandbox" building block: a stateless
string-in / value-out primitive an API endpoint could call per request. What it
does **not** do is isolate anything — see the gap below.

## The gap to a safe playground

`phel eval` runs with the full power of the host process. It is a developer
tool, not a sandbox. Two categories of escape hatch matter:

1. **`php/*` interop special forms** — `php/new`, `php/->`, `php/::`,
   `php/aget`, etc. (see `NAME_PHP_*` in `src/php/Lang/Symbol.php`). These reach
   arbitrary PHP:

   ```console
   $ phel eval '(php/getenv "HOME")'
   "/Users/you"
   ```

2. **Core functions that do I/O without any `php/*` form** — this is the
   important finding. File and code-loading primitives are plain corelib
   functions, reachable with no interop syntax at all:

   ```console
   $ phel eval '(take 20 (slurp "/etc/hosts"))'
   @["#" "#" "\n" "#" " " "H" "o" "s" "t" ...]
   ```

   - `slurp` / `spit` — read/write files (`src/phel/core/io.phel`)
   - `load-file` — load and run a file (`src/phel/core/ns.phel`)
   - `eval` / `read-string` — evaluate arbitrary code (`src/phel/core/protocols.phel`)

### Why a `--no-interop` flag is not enough

An obvious first move is a flag that rejects `php/*` forms at analysis time. It
was considered and **deliberately not shipped**: because `slurp`, `spit`,
`load-file`, and `eval` are ordinary core functions (not `php/*` interop), a
flag that only blocks `php/*` would leave the biggest escape hatches open. It
would read as a safety feature while providing no boundary — false confidence is
worse than none for a security-sensitive surface. Any real allowlist has to
operate on the *effective set of callable symbols*, not just interop syntax.

### What a real server-side sandbox needs (out of scope for one PR)

A trustworthy sandbox is a dedicated security project, not a compiler flag. It
needs, at minimum:

- **Symbol allowlist** — restrict the callable corelib surface (drop `slurp`,
  `spit`, `load-file`, `eval`, `read-string`, `sh`-style helpers) *and* reject
  `php/*` interop, enforced at analysis time so nothing dangerous reaches the
  emitter.
- **OS-level isolation** — the process still runs PHP `eval()`, so the allowlist
  is only defence-in-depth. The real boundary must be the container: no
  filesystem writes, no network, seccomp/gVisor-style syscall filtering, a
  read-only root, and a fresh short-lived process per request.
- **Resource limits** — CPU/wall-clock timeout, memory cap, output-size cap,
  recursion/stack guard (an infinite loop or huge allocation must be killed).
- **Abuse prevention** — rate limiting, request-size limits, per-IP quotas.

This surface deserves its own PR with a dedicated security review. It should not
be bolted onto `phel eval`.

## PHP-in-WASM path — feasibility (GO, pending PoC)

Running the compiler + runtime client-side via [php-wasm](https://github.com/seanmorris/php-wasm)
sidesteps the server sandbox entirely (nothing runs on our infra; the browser
tab is the sandbox). The language-side blockers were checked:

- **No hard PHP extension dependencies.** `composer.json` `require` is
  `php >=8.4`, `amphp/amp`, `gacela-project/gacela`, `symfony/console`,
  `symfony/routing` — all pure PHP, no `ext-*`. The only `ext-*` entry
  (`ext-readline`) is in `require-dev` and is used solely by the interactive
  REPL, not by eval.
- **Eval uses `eval()`, not `proc_open`.** The default `InMemoryEvaluator` calls
  PHP `eval()`, which php-wasm supports. `proc_open`/`pcntl`/`posix`/Fibers
  appear only in the parallel test runner, nREPL server, watcher, and async
  paths — none are on the compile-and-eval path a playground uses.

**Verdict: GO for a proof of concept.** No language-side hard blocker was found.
Open items a PoC must verify (not assumed here):

- php-wasm's bundled PHP version actually satisfies `>=8.4`.
- Initial payload size once the compiler + core `.phel` library are bundled, and
  cold-start compile time in the browser.
- That the pure-PHP runtime deps (amp, gacela, symfony) load cleanly under
  php-wasm's filesystem shim.

## Recommendation

- **Short term:** embed non-interactive, pre-rendered `:example` output in docs
  from the corelib metadata (no live eval, zero risk) while the playground is
  built.
- **Interactive playground:** prefer the **WASM path**. It removes the entire
  server-sandbox surface (isolation, resource limits, abuse prevention) that
  would otherwise each need building and reviewing. Gate it on a PoC that
  confirms the three open items above.
- **If a server API is chosen instead:** treat the sandbox as a standalone,
  security-reviewed project — symbol allowlist *plus* container isolation *plus*
  resource limits — and do not present a compiler flag alone as a boundary.

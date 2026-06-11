# nREPL Guide

`phel nrepl` starts a bencode-over-TCP nREPL server. Compatible with CIDER, Calva, vim-iced, and neovim-nrepl.

## Starting the server

```bash
./vendor/bin/phel nrepl                    # port 7888, host 127.0.0.1 (defaults)
./vendor/bin/phel nrepl --port=0           # bind a random free port
./vendor/bin/phel nrepl -p 7888            # -p is short for --port
./vendor/bin/phel nrepl --host=0.0.0.0 --port=7888
```

The server prints the bound `host:port` to stdout for client auto-discovery.

## Operations

| Op | Purpose |
|----|---------|
| `eval` | evaluate code, stream stdout/stderr/value |
| `clone` | fork a session (preserves ns and state) |
| `close` | close a session |
| `describe` | capability discovery |
| `load-file` | slurp-and-eval a file's contents |
| `interrupt` | acknowledged for editor compatibility (no-op: Phel evals synchronously, nothing to interrupt) |
| `completions` | return candidate completions for a prefix |
| `lookup` | return symbol metadata (arglists, doc, file:line) |
| `info` | equivalent to `lookup` under a different name |
| `eldoc` | inline signature hint for the function under point |

## Client setup

### Emacs (CIDER)

```elisp
(setq cider-default-cljs-repl nil)
;; M-x cider-connect-clj RET 127.0.0.1 RET 7888 RET
```

### VS Code (Calva)

Run `Calva: Connect to a running REPL`, choose `Generic nREPL`, and point at `127.0.0.1:7888`.

### Neovim (vim-iced or Conjure)

Both auto-detect a `.nrepl-port` file in the repo root. Phel does not write it, so create it with the bound port after launching the server.

## Pitfalls

- Single-user by default. Multiple clients sharing a session see interleaved output.
- Bind to `127.0.0.1` unless on a trusted network. No auth.
- `interrupt` is a no-op stub: Phel evaluates synchronously, so a running `eval` cannot be cancelled mid-flight. The op is acknowledged only to keep editors happy.

## See also

- [LSP Guide](./lsp-guide.md)
- [Quickstart](./quickstart.md): basic built-in REPL

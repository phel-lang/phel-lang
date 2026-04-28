# nREPL Guide

`phel nrepl` starts a bencode-over-TCP nREPL server for editor tooling and interactive development. Any nREPL client (CIDER, Calva, vim-iced, neovim-nrepl) can connect.

## Contents

- [Starting the server](#starting-the-server)
- [Operations](#operations)
- [Client setup](#client-setup)
- [Pitfalls](#pitfalls)

## Starting the server

```bash
./vendor/bin/phel nrepl                    # port 7888, 127.0.0.1
./vendor/bin/phel nrepl --port=0           # bind a random free port
./vendor/bin/phel nrepl --host=0.0.0.0 --port=7888
```

The server prints the bound port to stdout for client auto-discovery.

## Operations

| Op | Purpose |
|----|---------|
| `eval` | evaluate code, stream stdout/stderr/value |
| `clone` | fork a session (preserves ns and state) |
| `close` | close a session |
| `describe` | capability discovery |
| `load-file` | slurp-and-eval a file's contents |
| `interrupt` | stop a running `eval` in a session |
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

Both detect `.nrepl-port` in the repo root; set it after launching the server.

## Pitfalls

- Single-user by default; multiple clients sharing a session see interleaved output
- Bind to `127.0.0.1` unless on a trusted network; there is no auth
- `interrupt` stops the eval in that session only, not other sessions or fibers

## See also

- [LSP Guide](./lsp-guide.md)
- [Quickstart](./quickstart.md): basic built-in REPL

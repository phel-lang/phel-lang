# Language Server Guide

`phel lsp` speaks LSP v3.17 over stdio (JSON-RPC 2.0 with Content-Length framing). Provides hover, goto-definition, completion, references, rename, formatting, document/workspace symbols, and live diagnostics.

## Contents

- [Starting the server](#starting-the-server)
- [Capabilities](#capabilities)
- [Editor setup](#editor-setup)
- [Diagnostics](#diagnostics)
- [Pitfalls](#pitfalls)

## Starting the server

```bash
./vendor/bin/phel lsp
```

Reads LSP messages on stdin, writes responses on stdout, logs to stderr.

## Capabilities

| Feature | Method |
|---------|--------|
| Hover | `textDocument/hover` |
| Go to definition | `textDocument/definition` |
| Find references | `textDocument/references` |
| Completion | `textDocument/completion` |
| Rename | `textDocument/rename` |
| Formatting | `textDocument/formatting` |
| Document symbols | `textDocument/documentSymbol` |
| Workspace symbols | `workspace/symbol` |
| Diagnostics | `textDocument/publishDiagnostics` (debounced) |

## Editor setup

### VS Code

Install a generic LSP client extension and point it at `./vendor/bin/phel lsp` with `phel` as the language id and `.phel` as the file extension.

### Neovim (built-in LSP)

```lua
vim.lsp.start({
  name     = 'phel',
  cmd      = { './vendor/bin/phel', 'lsp' },
  filetypes = { 'phel' },
  root_dir  = vim.fs.dirname(vim.fs.find({ 'phel-config.php', 'composer.json' }, { upward = true })[1]),
})
```

### Emacs (`eglot`)

```elisp
(add-to-list 'eglot-server-programs
             '(phel-mode . ("./vendor/bin/phel" "lsp")))
```

## Diagnostics

Diagnostics include compiler errors, unresolved symbols, arity mismatches, and lint violations. Publication is debounced so typing does not thrash.

## Pitfalls

- The server scans files under the project root; keep `phel-config.php` current for require resolution
- Large projects benefit from running `phel index` ahead of time to warm the symbol cache
- LSP runs in its own PHP process; REPL state is not shared with a running `phel nrepl`

## See also

- [Linter Guide](./lint-guide.md)
- [nREPL Guide](./nrepl-guide.md)

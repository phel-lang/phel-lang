# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in Phel, please report it responsibly.

**Do not open a public issue.** Instead, email **phel@chemaclass.es** with:

- A description of the vulnerability
- Steps to reproduce
- Impact assessment (if possible)

You should receive a response within 48 hours. We'll work with you to understand and address the issue before any public disclosure.

## Supported Versions

Security fixes are applied to the latest release only. We recommend always running the most recent version.

## Scope

Phel compiles to PHP and runs in the PHP runtime. Security considerations include:

- **Compiler output**: Phel-generated PHP should not introduce vulnerabilities beyond what equivalent hand-written PHP would
- **PHP interop**: `(new Class ...)`, `(.method object ...)`, `(Class/method ...)` and the remaining `php/*` forms give full access to PHP — this is by design, not a vulnerability. The compiler still uses `php/new`, `php/->` and `php/::` internally, but rejects them in source
- **Dependencies**: We track CVEs in our Composer dependencies via Dependabot

## Running `phel` in a project you do not trust

Any `phel` command runs code from the project it starts in, not only `phel run`:

- `phel-config.php` is PHP, evaluated at startup. `phel` looks for it, and for `vendor/autoload.php`, in the working directory and then in each parent directory, and uses the first it finds.
- A `data-readers.phel` in the source dirs or the working directory is evaluated before `eval`, `doc`, `lint`, `compile`, `watch`, `lsp`, `nrepl` and the REPL do their own work.

So treat `phel lint` in a freshly cloned project like `composer install` with scripts enabled, and do not start `phel` from a directory whose parents other users can write to.

Generated PHP for `phel eval`, the REPL and `(load ...)` goes to a temp directory only the current user can open: `<system temp>/phel-<uid>/tmp`, created 0700. Phel refuses a temp directory another user owns.

`phel nrepl` has no authentication: anyone who reaches its port runs code as you. It binds `127.0.0.1` by default and warns when `--host` names anything else. To reach it from another machine, use an SSH tunnel.

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
- **`php/` interop**: PHP interop (`php/new`, `php/->`, `php/::`) gives full access to PHP — this is by design, not a vulnerability
- **Dependencies**: We track CVEs in our Composer dependencies via Dependabot

---
name: coding-style-naming-conventions
---

Follow PER-CS 3.0 enforced by php-cs-fixer and Rector, with strict types and short array syntax. Each PHP file starts
with `declare(strict_types=1);`. Prefer `final` classes unless inheritance is explicitly needed, and use `readonly`
properties where possible. Class and namespace names follow PascalCase, methods camelCase. Use `composer fix` to
auto-format and rely on Rector for mechanical refactors.

Phel source uses `;` for line comments (not `#`), `;;` for standalone comments, and kebab-case for functions and
variables. Public functions should have `:doc`, `:see-also`, and `:example` metadata.

Commas are optional whitespace (Clojure-aligned): use them between key/value pairs of a single-line map (`{:a 1, :b 2}`),
not in multi-line maps, vectors, lists, or calls. Inside a `` ` `` quasiquote `,` is unquote, so never put commas in
quasiquoted maps. `phel format` preserves commas but never inserts them.

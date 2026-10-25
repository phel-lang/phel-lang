# Phel Repository

Phel is a Lisp that compiles to PHP and follows Clojure-aligned semantics where practical.

Repository policy is defined by the rules generated below. Keep shared policy in `.agnostic-ai/`, Codex mechanics in
`.codex/`, Claude Code mechanics in `.claude/`, and downstream user assets in `resources/agents/`.

Paths in these instructions are relative to the repository root unless stated otherwise. Before editing, read the
directory-scoped instructions for the file. Before changing an accepted architectural choice, read its record in `docs/adr/`.
Before writing documentation, read `.agnostic-ai/rules/docs-placement.md` for its destination and link rules.
Before changing agent configuration (including `agnostic-ai.yaml`), read `.agnostic-ai/rules/agent-tooling.md`.

Start with one observable acceptance scenario. Trace the existing behavior, implement the smallest complete change,
then review the diff and run the applicable gate in `.agnostic-ai/rules/build-test-and-development-commands.md`.
Report what changed, the verification results, and any unresolved limit. Keep unrelated changes out of the diff.

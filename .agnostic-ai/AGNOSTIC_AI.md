# Phel Repository

Phel is a Lisp that compiles to PHP, with Clojure-aligned semantics where practical.

Paths are relative to the repository root. Before editing a file, read the instructions scoped to its directory.
Agent config is generated from `.agnostic-ai/`: edit there, never the output, and read
`.agnostic-ai/rules/agent-tooling.md` first (also for `agnostic-ai.yaml`). Before writing docs, read
`.agnostic-ai/rules/docs-placement.md`.

Start from one observable acceptance scenario. Trace the current behavior, make the smallest complete change, review
the diff, then run the gate in `.agnostic-ai/rules/build-test-and-development-commands.md`. Report what changed, the
checks run and any open limit. Keep unrelated changes out of the diff.

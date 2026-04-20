# Aider conventions: Phel

Phel is a Lisp that compiles to PHP. Pass this to Aider via `--read CONVENTIONS.md` or add to `.aider.conf.yml` under `read`.

- Hard rules, CLI, workflow: `.agents/RULES.md`
- Task recipes: `.agents/tasks/`
- Working examples: `.agents/examples/{todo-app, http-json-api, cli-wordcount}/`
- Deep reference: `docs/`, `src/phel/`

Commits: conventional (`feat:`, `fix:`, `ref:`, `chore:`, `docs:`, `test:`). No AI or LLM references in messages.

<p align="center">
  <img src="logo_readme.svg" width="350" alt="Phel logo"/>
</p>

<p align="center">
  <a href="https://github.com/phel-lang/phel-lang/actions">
    <img src="https://github.com/phel-lang/phel-lang/workflows/CI/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://github.com/phel-lang/phel-lang/blob/main/phpstan.neon">
    <img src="https://img.shields.io/badge/PHPStan-level%209-brightgreen" alt="PHPStan level 9">
  </a>
  <a href="https://github.com/phel-lang/phel-lang/blob/main/psalm.xml">
    <img src="https://img.shields.io/badge/Psalm-level%201-brightgreen" alt="Psalm level 1">
  </a>
  <a href="https://shepherd.dev/github/phel-lang/phel-lang">
    <img src="https://shepherd.dev/github/phel-lang/phel-lang/coverage.svg" alt="Psalm Type-coverage Status">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/phel-lang/phel-lang">
    <img src="https://img.shields.io/packagist/v/phel-lang/phel-lang" alt="Packagist Version">
  </a>
  <a href="https://packagist.org/packages/phel-lang/phel-lang/stats">
    <img src="https://img.shields.io/packagist/dt/phel-lang/phel-lang" alt="Packagist Downloads">
  </a>
  <a href="https://packagist.org/packages/phel-lang/phel-lang">
    <img src="https://img.shields.io/packagist/php-v/phel-lang/phel-lang" alt="PHP Version Required">
  </a>
  <a href="https://github.com/phel-lang/phel-lang/blob/main/LICENSE">
    <img src="https://img.shields.io/github/license/phel-lang/phel-lang" alt="License">
  </a>
  <a href="https://deepwiki.com/phel-lang/phel-lang">
    <img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki">
  </a>
</p>

---

Lisp for PHP, macros, persistent data structures, REPL.

## Get Started

```sh
composer require phel-lang/phel-lang
```

**1. Open a REPL**

```sh
./vendor/bin/phel repl
```

```clojure
phel:1:> (->> [1 2 3 4 5] (filter odd?) (map #(* % %)) (reduce +))
35
phel:2:> (defn greet [name] (str "Hello, " name "!"))
| user/greet
phel:3:> (greet "Phel")
| "Hello, Phel!"
```

**2. Scaffold a project**

```sh
./vendor/bin/phel init         # add `--minimal` for a single-file layout
```

Creates `phel-config.php`, `src/phel/main.phel`, `tests/phel/main_test.phel`. Then:

```sh
./vendor/bin/phel run src/phel/main.phel   # run
./vendor/bin/phel test                     # tests
./vendor/bin/phel build                    # compile to PHP
./vendor/bin/phel config                   # inspect the merged config
```

**3. Eval inline or via stdin**

```sh
./vendor/bin/phel eval '(+ 1 2)'           # prints 3
echo '(println "hi")' | ./vendor/bin/phel eval -
./vendor/bin/phel eval - < script.phel
```

**4. Enable shell completion (optional)**

`./vendor/bin/phel completion` dumps a tab-completion script for `bash`, `zsh`, or `fish`. It completes commands, their options, and dynamic values (function names for `doc`, project namespaces for `run`/`test`).

```sh
# bash — restart your shell afterwards
./vendor/bin/phel completion bash | sudo tee /etc/bash_completion.d/phel

# zsh — write into a directory on your $fpath
./vendor/bin/phel completion zsh > "${fpath[1]}/_phel"

# fish
./vendor/bin/phel completion fish > ~/.config/fish/completions/phel.fish
```

The zsh script starts with `#compdef phel`, so completion only triggers for a binary named `phel` on your `$PATH`. If you call `./vendor/bin/phel` (or `./bin/phel` in a dev checkout), symlink a global `phel` first, e.g. on macOS + Homebrew:

```sh
phel completion zsh > "$(brew --prefix)/share/zsh/site-functions/_phel"
ln -sf "$PWD/bin/phel" "$(brew --prefix)/bin/phel"   # global `phel` so #compdef matches
rm -f ~/.zcompdump*                                  # force compinit to rebuild
# then open a new shell
```

> Prefer a project template? [`web-skeleton`](https://github.com/phel-lang/web-skeleton) or [`cli-skeleton`](https://github.com/phel-lang/cli-skeleton): click **Use this template** for a one-click start.

<details>
<summary><b>More examples →</b></summary>

<table>
<tr>
<td width="50%" valign="top">

**Data pipeline**

```clojure
(def users
  [{:name "Ada" :age 36}
   {:name "Bob" :age 17}
   {:name "Cam" :age 41}])

(->> users
     (filter #(>= (:age %) 18))
     (map :name)
     sort)
;; => ["Ada" "Cam"]
```

</td>
<td width="50%" valign="top">

**HTTP response**

```clojure
(ns app (:require phel.http :as h))

(def req (h/request-from-globals))

(h/emit-response
  (h/response-from-map
    {:status 200
     :headers {"Content-Type" "text/plain"}
     :body (str "Hello " (:uri req))}))
```

</td>
</tr>
<tr>
<td valign="top">

**Macros**

```clojure
(defmacro unless [pred & body]
  `(if (not ~pred)
     (do ~@body)))

(unless (zero? 1)
  (println "not zero"))
;; => not zero

(unless false (println "ok"))
;; => ok
```

</td>
<td valign="top">

**PHP interop**

```clojure
(ns app)

(def now (php/new \DateTime))
(.format now "Y-m-d")
;; => "2026-04-20"

(def epoch (php/new \DateTime "1970-01-01"))
(.-days (.diff now epoch))
;; => 20564
```

</td>
</tr>
</table>
</details>

## Documentation

- [Getting Started](https://phel-lang.org/documentation/getting-started/): install, REPL, first script (5 min)
- [CLI Reference & DX Guide](docs/cli-reference.md): every command, the dev loop, compile vs eval vs run vs build
- [phel-lang.org](https://phel-lang.org/documentation/): full documentation, tutorials, exercises, blog
- [Contributor docs](docs/README.md): repository internals, agent tooling, project layout
- [Packagist](https://packagist.org/packages/phel-lang/phel-lang)
- [CONTRIBUTING.md](.github/CONTRIBUTING.md): setup and workflow
- [AGENTS.md](AGENTS.md): architecture and review expectations

## AI Coding Agents

Skill files for Claude Code, Cursor, Codex, Gemini, Copilot, Aider: [resources/agents/](resources/agents/README.md)

```sh
./vendor/bin/phel agent-install [platform]   # install skill file for one agent (claude, cursor, ...)
./vendor/bin/phel agent-install --auto       # only agents detected in this project
./vendor/bin/phel agent-install --all        # every supported platform
```

### Repo-level AI tooling

Claude Code (`.claude/`) and Codex (`.codex/`, `.agents/`, `AGENTS.md`) configs generate from a single source tree under [.agnostic-ai/](.agnostic-ai/) via [agnostic-ai](https://github.com/Chemaclass/agnostic-ai). Those directories are gitignored; run `sync` after cloning to materialize them. Add more targets (Gemini, Cursor, ...) by appending to `targets:` in `agnostic-ai.yaml`.

```sh
brew install Chemaclass/tap/agnostic-ai   # or: go install github.com/chemaclass/agnostic-ai/cmd/agnostic-ai@latest
agnostic-ai sync                          # regenerate per-tool configs
```

Edit specs under `.agnostic-ai/{rules,agents,skills,hooks,scripts,overlays}/`, then `agnostic-ai sync` again. A CI gate runs `sync --check` on every PR to block drift between the source and the (gitignored) emitted files.

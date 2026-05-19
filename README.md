<p align="center">
  <img src="logo_readme.svg" width="350" alt="Phel logo"/>
</p>

<p align="center">
  <a href="https://github.com/phel-lang/phel-lang/actions">
    <img src="https://github.com/phel-lang/phel-lang/workflows/CI/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://github.com/phel-lang/phel-lang/blob/main/phpstan.neon">
    <img src="https://img.shields.io/badge/PHPStan-level%206-brightgreen" alt="PHPStan level 6">
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

### Try it in 30 seconds

```sh
composer require phel-lang/phel-lang
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

> Prefer a project template? [`web-skeleton`](https://github.com/phel-lang/web-skeleton) or [`cli-skeleton`](https://github.com/phel-lang/cli-skeleton): click **Use this template** for a one-click start.

### Example
<!--
using "clojure" here is just for the md coloring
we should use "phel" once GitHub accept phel coloring too
-->
```clojure
(ns my.example)

(defn greet [name] 
  (str "Hello, " name "!"))

(println (greet "Phel"))
;; => Hello, Phel!
```

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

## Getting Started

```sh
composer require phel-lang/phel-lang
./vendor/bin/phel init                     # add `--minimal` for a single-file layout
```

Scaffolds `phel-config.php`, `src/phel/main.phel`, `tests/phel/main_test.phel`.

```sh
./vendor/bin/phel run src/phel/main.phel   # run
./vendor/bin/phel test                     # tests
./vendor/bin/phel repl                     # REPL
./vendor/bin/phel build                    # compile to PHP
```

Eval inline or via stdin:

```sh
./vendor/bin/phel eval '(+ 1 2)'           # prints 3
echo '(println "hi")' | ./vendor/bin/phel eval -
./vendor/bin/phel eval - < script.phel
```

## Documentation

- [Quick Start](docs/quickstart.md): install, REPL, first script (5 min)
- [Documentation Index](docs/README.md): every guide, grouped by purpose
- [phel-lang.org](https://phel-lang.org): tutorials, exercises, blog
- [Packagist](https://packagist.org/packages/phel-lang/phel-lang)
- [CONTRIBUTING.md](.github/CONTRIBUTING.md): setup and workflow
- [AGENTS.md](AGENTS.md): architecture and review expectations

## AI Coding Agents

Skill files for Claude Code, Cursor, Codex, Gemini, Copilot, Aider: [resources/agents/](resources/agents/README.md)

```sh
./vendor/bin/phel agent-install [platform] [--all]   # install skill file for your agent
```

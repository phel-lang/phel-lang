# CLI tool

Use `phel\cli` — data-driven wrapper over `symfony/console` (bundled, no extra deps). Describe commands as Phel maps; get subcommands, args, options, prompts, tables, progress bars, shell completion, signals, test helpers.

Full reference: `docs/cli-guide.md`. Module: `src/phel/cli.phel`.

## Quickstart

`src/main.phel`:

```phel
(ns my-tool\main
  (:require phel\cli :as cli))

(defn- greet [ctx]
  (let [name (or (cli/arg ctx "name")
                 (cli/ask ctx "What's your name?" "world"))]
    (cli/success ctx (str "Hello, " name "!"))))

(def app
  (cli/application
    {:name     "my-tool"
     :version  "1.0.0"
     :default  "greet"
     :commands [{:name "greet"
                 :doc  "Say hi"
                 :args [{:name "name" :mode :optional :doc "Who to greet"}]
                 :run  greet}]}))

(when-not *build-mode* (php/exit (cli/run app)))
```

```bash
./vendor/bin/phel run src/main.phel greet alice
# [OK] Hello, alice!
```

## Command spec keys

| Key | Type | Purpose |
|-----|------|---------|
| `:name` | string (req) | Command name, e.g. `"build"` or `"app:build"` |
| `:doc` | string | Short description (`list` output) |
| `:help` | string | Long help (`<cmd> --help`) |
| `:aliases` | vec string | Alternate names |
| `:hidden?` | bool | Hide from `list` |
| `:args` | vec map | Positional arg specs |
| `:opts` | vec map | Option specs |
| `:run` | fn (req) | `(fn [ctx] ...)` — see handler contract |

## Arg / opt specs

| Key | Arg mode | Opt mode |
|-----|----------|----------|
| `:mode` | `:required` `:optional` `:array` | `:none` `:required` `:optional` `:array` `:negatable` |
| `:coerce` | `:int` `:float` `:bool` `:keyword` `:edn` | same |
| `:name` `:doc` `:default` | yes | yes |
| `:short` | — | single char, e.g. `"v"` |
| `:complete` | `(fn [input] [...])` | `(fn [input] [...])` |

Read inside handler: `(cli/arg ctx "name")` / `(cli/opt ctx "verbose")`. Coercion auto-applied.

## Handler contract

Receives context map:

```phel
{:input <InputInterface> :output <OutputInterface>
 :arg-specs [...] :opt-specs [...] :style <SymfonyStyle>}
```

Return `nil`/`0` = success, any int = exit code. Uncaught `Throwable` → red `<error>` + exit `1`; `-v` adds stack trace.

Never call `php/exit` in handlers (except signal handlers).

## Output

```phel
(cli/writeln ctx "plain line")
(cli/write   ctx "no newline")
(cli/info    ctx "green info")
(cli/comment-line ctx "yellow")
(cli/error   ctx "red")

;; Verbosity-aware (-v / -vv / -vvv)
(cli/info-v  ctx "detail")
(cli/info-vv ctx "more")
(cli/debug   ctx "-vvv only")

;; SymfonyStyle boxes
(cli/title ctx "Report")  (cli/section ctx "Sub")
(cli/success ctx "OK!")   (cli/warning ctx "!")
(cli/note ctx "fyi")      (cli/caution ctx "danger")
(cli/listing ctx ["a" "b" "c"])
```

## Prompts

```phel
(cli/ask        ctx "Your name?" "world")
(cli/ask-hidden ctx "Password?")
(cli/confirm    ctx "Deploy?" false)
(cli/choice     ctx "Env?" ["dev" "stg" "prod"] "dev")
```

## Tables + progress

```phel
(cli/table ctx ["id" "name"] [[1 "alice"] [2 "bob"]]
  {:style :markdown :widths [6 20]})

;; Styles: :default :borderless :compact :symfony :box :box-double :markdown

(cli/run-with-progress ctx files
  (fn [f _bar] (process-file f)))

(let [bar (cli/progress-bar ctx {:max 100 :format :verbose})]
  (cli/progress-start bar)
  (dotimes [_ 100] (do-work) (cli/progress-advance bar))
  (cli/progress-finish bar))
```

## Stdin

```phel
;; cat data.txt | my-tool process
(for [line :in (cli/stdin-lines) :when (not= line "")]
  (handle-line line))
```

`stdin-lines` handles `php/fgets` false-at-EOF; don't reimplement.

## Shell completion

```phel
{:args [{:name "env" :mode :required
         :complete (fn [_input] ["dev" "staging" "prod"])}]}
```

Install: `my-tool _complete --generate-hook=bash > ~/.bash_completion.d/my-tool`.

## Signal handling + hooks

```phel
(cli/application
  {:name      "mytool"
   :before    (fn [e] ...) ; ConsoleCommandEvent
   :after     (fn [e] ...) ; ConsoleTerminateEvent
   :on-error  (fn [e] ...) ; ConsoleErrorEvent
   :on-signal {:sigint  (fn [_] (cleanup) (php/exit 130))
               :sigterm (fn [_] (cleanup))}
   :commands  [...]})
```

## Entry points

`phel run`: `(when-not *build-mode* (php/exit (cli/run app)))`.

Composer bin shim `bin/greet`:

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
\Phel::run(__DIR__ . '/..', 'my-tool\\main');
```

`composer.json`: `{ "bin": ["bin/greet"] }`, then `chmod +x bin/greet`.

## Tests

```phel
(ns tests\main-test
  (:require phel\test :refer [deftest is])
  (:require phel\cli  :as cli)
  (:require my-tool\main :refer [app]))

(deftest greets
  (let [input  (cli/argv ["greet" "alice"])
        output (cli/buffered-output)
        code   (cli/run app input output)]
    (is (= 0 code))
    (is (cli/output-contains? output "Hello, alice!"))))

(deftest prompts-canned-stdin
  (let [input  (cli/argv-with-stdin ["greet"] "carol\n")
        output (cli/buffered-output)]
    (cli/run app input output)
    (is (cli/output-contains? output "Hello, carol!"))))
```

## Gotchas

- `:auto-exit?` defaults to `false`; `(cli/run app)` returns exit code — wrap with `(php/exit ...)` at top level.
- `:default` sets the command to run when no subcommand given.
- `cli/arg` / `cli/opt` apply `:coerce` once — don't re-coerce in handler.
- Spec validation is eager; bad maps throw `InvalidArgumentException` at build time.
- Prefer `cli/success`, `cli/error` over raw ANSI.
- Lift handlers to top-level `defn-` for testability.

## Raw approach (minimal CLI, no Symfony)

For a trivial script without subcommands, drop `phel\cli`:

```phel
(ns my-tool\main)

(defn -main [& args]
  (println (str "Hello, " (or (first args) "World") "!"))
  0)

(when-not *build-mode* (php/exit (apply -main *argv*)))
```

Example: `.agents/examples/cli-wordcount/`.

## Next

`docs/cli-guide.md`, `src/phel/cli.phel`, `tasks/add-tests.md`, `docs/framework-integration.md`

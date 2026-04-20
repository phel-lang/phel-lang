# Building CLIs with `phel\cli`

`phel\cli` is a data-driven wrapper over [`symfony/console`](https://symfony.com/doc/current/components/console.html) that lets you build full-featured command-line tools in Phel — subcommands, arguments, options, interactive prompts, tables, progress bars, shell completion, signal handling — all described as plain Phel maps.

Because `symfony/console` is already a runtime dependency of `phel-lang`, using `phel\cli` adds zero new dependencies.

## Quickstart

```phel
(ns my-tool\main
  (:require phel\cli :as cli))

(defn- greet [ctx]
  (let [name (or (cli/arg ctx "name")
                 (cli/ask ctx "What's your name?" "world"))]
    (cli/success ctx (str "Hello, " name "!"))))

(defn -main [& args]
  (cli/run
    (cli/application
      {:name     "my-tool"
       :version  "1.0.0"
       :default  "greet"
       :commands [{:name "greet"
                   :doc  "Say hi"
                   :args [{:name "name" :mode :optional
                           :doc "Who to greet"}]
                   :run  greet}]})))
```

Run:

```
$ phel run my-tool/main greet alice
[OK] Hello, alice!
```

## Spec reference

### Command spec

| Key         | Type              | Description                                         |
| ----------- | ----------------- | --------------------------------------------------- |
| `:name`     | string (required) | Command name, e.g. `"build"` or `"app:build"`       |
| `:doc`      | string            | Short description shown in `list`                   |
| `:help`     | string            | Long help text shown in `<cmd> --help`              |
| `:aliases`  | vector of strings | Alternate names users can type                      |
| `:hidden?`  | bool              | Hide from `list` but still invocable                |
| `:args`    | vector of maps    | Positional argument specs — see below               |
| `:opts`    | vector of maps    | Option (flag) specs — see below                     |
| `:run`     | fn (required)     | Handler `(fn [ctx] ...)` — see "Handler contract"   |

### Argument spec (`:args`)

| Key         | Type                              | Description                                        |
| ----------- | --------------------------------- | -------------------------------------------------- |
| `:name`     | string (required)                 | Arg name — used to read via `(arg ctx name)`       |
| `:mode`     | `:required` / `:optional` / `:array` | Default `:optional`                              |
| `:doc`      | string                            | Shown in help output                               |
| `:default`  | any                               | Default value when omitted                         |
| `:coerce`   | `:int` / `:float` / `:bool` / `:keyword` / `:edn` | Value conversion applied by `arg`   |
| `:complete` | fn                                | `(fn [input] [...])` — shell completion suggestions |

### Option spec (`:opts`)

| Key         | Type                                                              | Description                      |
| ----------- | ----------------------------------------------------------------- | -------------------------------- |
| `:name`     | string (required)                                                 | Option name — the `--name` flag  |
| `:short`    | string                                                            | Single-char shortcut — e.g. `"v"` |
| `:mode`     | `:none` / `:required` / `:optional` / `:array` / `:negatable`     | Default `:optional`              |
| `:doc`      | string                                                            | Shown in help                    |
| `:default`  | any                                                               | Default when flag omitted        |
| `:coerce`   | `:int` / `:float` / `:bool` / `:keyword` / `:edn`                 | Value conversion                 |
| `:complete` | fn                                                                | Shell completion suggestions     |

### Application spec

| Key           | Type                   | Description                                                       |
| ------------- | ---------------------- | ----------------------------------------------------------------- |
| `:name`       | string                 | Application name (shown in help header)                           |
| `:version`    | string                 | Version string                                                    |
| `:commands`   | vector of command specs| Commands to register                                              |
| `:default`    | string                 | Name of command to run when no arg passed                         |
| `:auto-exit?` | bool                   | When `false` (default), `run` returns exit code instead of `exit` |
| `:before`     | fn                     | Hook on `ConsoleCommandEvent` — receives raw event                |
| `:after`      | fn                     | Hook on `ConsoleTerminateEvent`                                   |
| `:on-error`   | fn                     | Hook on `ConsoleErrorEvent`                                       |
| `:on-signal`  | map                    | `{:sigint cleanup-fn :sigterm cleanup-fn …}`                      |

## Handler contract

Your `:run` handler receives a **context map**:

```phel
{:input     <InputInterface>
 :output    <OutputInterface>
 :arg-specs <vector of arg specs>
 :opt-specs <vector of opt specs>
 :style     <SymfonyStyle>}
```

Return:

- `nil` or `0` → success
- any other `int` → exit code

Uncaught `Throwable` becomes a red `<error>` line plus exit code `1`. With `-v`, the stack trace is also written.

## Writing to output

```phel
(cli/writeln ctx "plain line")
(cli/write ctx "no newline")

(cli/info         ctx "green info")
(cli/comment-line ctx "yellow comment")
(cli/error        ctx "red error")

;; Verbosity-aware — only emit when user passes -v / -vv / -vvv
(cli/info-v   ctx "extra detail")
(cli/info-vv  ctx "even more")
(cli/debug    ctx "only in -vvv")
```

## Styled output (SymfonyStyle)

```phel
(cli/title   ctx "Report")
(cli/section ctx "Summary")
(cli/success ctx "All good!")
(cli/warning ctx "Something suspicious")
(cli/note    ctx "FYI")
(cli/caution ctx "Danger")
(cli/listing ctx ["step 1" "step 2" "step 3"])
```

## Interactive prompts

```phel
(cli/ask         ctx "Your name?" "world")       ; free-text with default
(cli/ask-hidden  ctx "Password?")                ; no echo
(cli/confirm     ctx "Deploy?" false)            ; y/n, default no
(cli/choice      ctx "Env?" ["dev" "stg" "prod"] "dev")
```

## Tables

```phel
(cli/table ctx
  ["id" "name" "status"]
  [[1 "alice" "ok"]
   [2 "bob"   "fail"]])

;; With options:
(cli/table ctx headers rows {:style :markdown :widths [10 20]})
```

Available `:style` values: `:default`, `:borderless`, `:compact`, `:symfony`, `:box`, `:box-double`, `:markdown`.

## Progress bars

```phel
;; Easy form — iterate and let cli manage start/advance/finish
(cli/run-with-progress ctx files
  (fn [f _bar] (process-file f)))

;; Manual control
(let [bar (cli/progress-bar ctx {:max 100 :format :verbose})]
  (cli/progress-start bar)
  (dotimes [i 100]
    (do-work i)
    (cli/progress-advance bar))
  (cli/progress-finish bar))
```

## Shell completion

```phel
{:args [{:name "env" :mode :required
         :complete (fn [_input] ["dev" "staging" "prod"])}]}
```

Symfony calls your completion function when the user presses `<TAB>` after installing the shell completion script:

```
$ my-tool _complete --generate-hook=bash > ~/.bash_completion.d/my-tool
```

## Signal handling

```phel
(cli/application
  {:name     "mytool"
   :on-signal {:sigint  (fn [_] (println "Cleaning up...") (php/exit 130))
               :sigterm (fn [_] (cleanup-fn))}
   :commands [...]})
```

## Stdin piping

```phel
;; cat data.txt | my-tool process
(for [line :in (cli/stdin-lines) :when (not= line "")]
  (handle-line line))
```

## Testing your CLI

`phel\cli` ships test helpers so handlers can be exercised without spawning a process:

```phel
(ns my-tool-test\test\main
  (:require phel\cli :as cli)
  (:require phel\test :refer [deftest is]))

(deftest test-greet
  (let [spec   {:name "greet"
                :args [{:name "who" :mode :required}]
                :run  (fn [ctx] (cli/writeln ctx (str "hi " (cli/arg ctx "who"))))}
        app    (cli/application "test" [spec])
        input  (cli/argv ["greet" "alice"])
        output (cli/buffered-output)
        code   (cli/run app input output)]
    (is (= 0 code))
    (is (cli/output-contains? output "hi alice"))))
```

For prompt testing, pipe canned STDIN:

```phel
(let [input  (cli/argv-with-stdin ["ask-name"] "alice\n")
      output (cli/buffered-output)]
  (cli/run app input output)
  (is (cli/output-contains? output "hi alice")))
```

## Best practices

- **Spec validation is eager.** Malformed maps throw `InvalidArgumentException` at build time with a Phel-friendly message — catch bugs before ship.
- **Return exit codes.** Never call `php/exit` from a handler (except in signal handlers). Return an `int`, and `phel\cli` takes care of it.
- **Use `:coerce`.** Don't `(php/intval (arg ctx "port"))` in every handler — specify `:coerce :int` once.
- **Lift handlers to top-level fns.** Keeps `:run` small and testable.
- **Keep `:default`** set to a safe command. If users run your tool with no args and there's no default, Symfony shows help — usually fine, but explicit is nicer.

## See also

- [`phel\router`](../src/phel/router.phel) — companion for HTTP apps
- [`phel\http-client`](../src/phel/http-client.phel) — outbound HTTP
- [symfony/console docs](https://symfony.com/doc/current/components/console.html) — full underlying API

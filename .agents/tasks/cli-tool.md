# CLI tool

Two shapes: `phel run` script, or PHP shim binary.

## A. `phel run` script

`src/main.phel`:

```phel
(ns my-cli\main)

(defn -main [& args]
  (println (str "Hello, " (or (first args) "World") "!"))
  0)

(when-not *build-mode*
  (apply -main *argv*))
```

```bash
./vendor/bin/phel run src/main.phel Alice
```

## B. Composer bin shim

`bin/greet`:

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
\Phel::run(__DIR__ . '/..', 'my-cli\\main', array_slice($argv, 1));
```

```bash
chmod +x bin/greet
```

`composer.json`:

```json
{ "bin": ["bin/greet"] }
```

`src/main.phel` same as A. `*argv*` holds user args only.

## Stdin

```phel
(def stdin (php/fopen "php://stdin" "r"))

(defn read-all []
  (loop [acc ""]
    (let [chunk (php/fgets stdin)]
      (if (or (false? chunk) (nil? chunk))
        acc
        (recur (str acc chunk))))))

(when-not *build-mode* (println (read-all)))
```

Never call blocking stdin at top level unguarded; `phel build` hangs.

## Argv parsing

```phel
(let [[cmd & rest] *argv*]
  (case cmd
    "help"    (print-help)
    "version" (println "1.0.0")
    (exec rest)))
```

For flags, use Symfony Console via interop.

## Exit codes

Return int from `-main`. `(php/exit n)` forces immediate exit.

## Output

`println` / `print`. Stderr: `(php/fwrite php/STDERR (str msg "\n"))`.

## Tests

```phel
(ns tests\main-test
  (:require phel\test :refer [deftest is])
  (:require my-cli\main :refer [-main]))

(deftest greets
  (is (= 0 (-main "Alice"))))
```

Runnable: `.agents/examples/cli-wordcount/`.

## Gotchas

- `*argv*` under `phel run` excludes file path. Under shim, pass `array_slice($argv, 1)`.
- `php/fgets` returns `false` at EOF, not `nil`.

## Next

`tasks/add-tests.md`, `docs/framework-integration.md`

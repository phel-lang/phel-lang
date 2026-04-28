# CLI word count

Reads stdin or file args, prints line, word, character counts. Shows `phel run`, argv parsing, stdin handling, and the `*build-mode*` guard. Flat layout, no `phel-config.php` (auto-detected).

## Run

```bash
composer install
./vendor/bin/phel test
echo "hello world" | ./vendor/bin/phel run src/main.phel
./vendor/bin/phel run src/main.phel README.md
./bin/wordcount README.md
```

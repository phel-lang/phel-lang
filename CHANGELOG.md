# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

* Added: `merge-with` function
* Added: `deep-merge` function
* Added: `NamedInterface` interface for Symbol and Keyword
* Added: `name` function
* Added: `namespace` function
* Added: `full-name` function
* Added: `http/uri-from-string` function
* Added: Uri structs implements `Stringable` interface
* Added: `http/response-from-map` function
* Deprecated: `http/create-response-from-map` in favor of `http/response-from-map`
* Added: `http/response-from-string` function
* Deprecated: `http/create-response-from-string` in favor of `http/response-from-string`
* Added: `attributes` field to `request` struct. Allows developers to enrich the request with custom data
* Added: `http/request-from-map` function
* Bugfix: #443
* Added: Support for PHP Array literals (#451)
* Added: `read-string` function
* Added: `eval` function
* Added: `compile` function
* Bugfix: #467 Failed to run a REPL on Windows
* Bugfix: #471 Reusing a local variable in a function fails.

## 0.6.0 (2022-02-02)

* Drop support for PHP 7.4. (#403)
* Support for PHP 8.0 and 8.1. (#406)
* Printer: render `<function>` when printing a function and not `<PHP-AnonymousClass>`. (#404)
* Add a `:reduce` option for the for-loop. (#405)
* Removed deprecated table, array and set mutable data structures. (#407)
* Add feature to require php files in ns statement. (#421)
* Remove all calls to GlobalEnvironmentSingleton in the compiled code. (#408)
* Add a new phel core function: `coerce-in` (#424)
* Introduce a registry class to store the definitions instead of `$GLOABLS`. (#423)
* Evaluate meta data in the special form `def` (#426)
* Add support for inline optimization. (#427)
* Fixed bug in compile command (#428, #410)
* Improved documentation (#432)

## 0.5.0 (2021-12-17)

* Added variables.
* Added namespaced keywords.
* Added interfaces and their usage in structs.
* Added a compile command

## 0.4.0 (2021-10-05)

* Removed `load` function in `phel\core`
* Pass by value the array (1st argument) to `push` (#306)
* **Breaking**: Configuration will be loaded from `phel-config.php` and not from `composer.json`
  * The `loader` config parameter has been removed. Please use `src-dirs` now.
  * The `loader-dev` config parameter has been removed. Please use `test-dirs` now.
  * The `tests` config parameter has been removed. Please use `test-dirs` now.
  * A `vendor-dir` config parameter has been introduced. Default value is `vendor`.
* **Breaking**: Dependencies in vendor will only be recognized if the vendor project has a `phel-config.php` file. All old project that have the configuration inside the `composer.json` will not be detected anymore.
* The `phel-composer-plugin` is obsolete and is not need it anymore.
* The way code in Phel is compiled has changed:
  * Before it was bottom up: If a phel file was evaluated it continued only after all dependencies have been evaluated.
  * Now it is top down: The compiler first creates a dependencies graph and start to evaluate files with no dependencies before others.
* The `PhelRuntime` was removed and is not needed anymore.
* Internal refactoring:
  * All commands have been moved to their associated modules.

## 0.3.3 (2021-06-04)

* Removed `load` function.
* Fixed `RangeIterator` for Vectors (#302)

## 0.3.2 (2021-05-25)

* Transient Maps can grow bigger than 16 elements (#289)
* Added a filter option to the test command. (#285)
* Added execution time and resource usage to the test command (#284)
* Disallows unexpected keywords in ns (#286)

## 0.3.1 (2021-05-16)

* For loop will now return a vector instead of an array (#276)

## 0.3.0 (2021-05-12)

* New persistent data structures (#244)
  - The old data structures have been deprecated and will be removed in the next version.
* Rename `fmt` command to `format` (#248)
* Added new function `take-last` (#245)
* Added new function `re-seq` (#245)
* `partition` now returns all items if the length of the given array is lower than the given size n. (#246)
* `partition` now returns remaining items if the size of the remaining array is lower than given size n. (#246)
* Added new function `contains?` (#267)

## 0.2.0 (2021-02-22)

* Call Phel functions from PHP (#209)
* Set PHP object properties from Phel (#235)

## 0.1.0 (2021-01-31)

Initial release

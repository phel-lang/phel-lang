# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

* Removed `load` function in `phel\core`

## 0.3.2 (2021-05-16)

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

## 0.1.0 (2021-31-01)

Initial release
